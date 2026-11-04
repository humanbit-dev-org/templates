<?php

namespace App\Http\Controllers\Restricted;

use App\Models\User;
use App\Services\Auth\SamlSignatureVerifier;
use App\Services\Logging\SamlSimulationLogService;
use App\Services\Restricted\SamlSimulationScenarioService;
use Dedoc\Scramble\Attributes\ExcludeRouteFromDocs;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * Auth Simulation: lets an operator simulate a SAML login, picking the user
 * by hand or through a scenario (a random user in a given state). The page
 * builds a SAMLResponse and auto-posts it to authSaml().
 *
 * authSaml() itself is NOT a simulation: it is the real SAML endpoint logic.
 * Reached through the simulation route (auth.sim.saml, behind the operator
 * shield) it skips the signature check; reached through a public route it
 * requires a valid IdP signature (config/saml.php).
 */
class SamlController extends Controller
{
    public function __construct(
        protected SamlSimulationScenarioService $scenarios,
        protected SamlSimulationLogService $simulationLog,
    ) {}

    #[ExcludeRouteFromDocs]
    public function form(Request $request): View
    {
        return view('auth-simulation.index', [
            'postAction' => route('auth.sim.post'),
            'relay' => (string) $request->query('relay', ''),
            'scenarios' => array_values($this->scenarios->catalog()),
            'errorScenarios' => array_values($this->scenarios->errorCatalog()),
            'logs' => $this->simulationLog->paginate($request),
            'operator' => $this->scenarios->operatorContext(),
        ]);
    }

    /**
     * Build the SAMLResponse of the chosen user and auto-post it to the
     * simulation twin of the SAML endpoint.
     */
    #[ExcludeRouteFromDocs]
    public function autoPost(Request $request): View|RedirectResponse
    {
        $validated = $request->validate([
            'username' => ['nullable', 'string', 'max:255'],
            'RelayState' => ['nullable', 'string'],
            'scenario' => ['nullable', 'string'],
        ]);

        $scenario = (string) ($validated['scenario'] ?? SamlSimulationScenarioService::SCENARIO_MANUAL);
        if (! $this->scenarios->isKnownScenario($scenario)) {
            $scenario = SamlSimulationScenarioService::SCENARIO_MANUAL;
        }

        $username = trim((string) ($validated['username'] ?? ''));
        $relayState = $validated['RelayState'] ?? null;

        if ($scenario !== SamlSimulationScenarioService::SCENARIO_MANUAL) {
            $username = (string) $this->scenarios->randomUsernameForScenario($scenario);

            if ($username === '') {
                return redirect()
                    ->route('auth.sim.form', array_filter(['relay' => $relayState]))
                    ->withErrors(['scenario' => 'Nessun utente disponibile per lo scenario selezionato.']);
            }
        }

        if ($username === '') {
            return redirect()
                ->route('auth.sim.form', array_filter(['relay' => $relayState]))
                ->withInput()
                ->withErrors(['username' => 'Inserisci uno username.']);
        }

        $operator = $this->scenarios->operatorContext();
        $this->simulationLog->append([
            'simulated_user_id' => $username,
            'scenario' => $scenario,
            'scenario_label' => $this->scenarios->labelFor($scenario),
            'operator_user_id' => $operator['user_id'] ?? null,
            'operator_email' => $operator['email'] ?? null,
            'operator_role' => $operator['role'] ?? null,
        ]);

        Log::info('Auth simulation started', [
            'scenario' => $scenario,
            'simulated_username' => $username,
            'operator_email' => $operator['email'] ?? null,
        ]);

        return view('auth-simulation.posting', [
            // The simulator posts to the TWIN route (behind the operator shield),
            // never to a public endpoint: that one requires a valid signature,
            // which the simulator cannot produce.
            'acs' => route('auth.sim.saml'),
            'samlResponse' => base64_encode($this->buildSamlResponse($username)),
            'relayState' => $relayState,
            'simulatedUserId' => $username,
        ]);
    }

    /**
     * SAML assertion consumer: log the user in and hand the frontend an API
     * token through the apiToken cookie.
     */
    #[ExcludeRouteFromDocs]
    public function authSaml(Request $request): RedirectResponse|Response
    {
        $b64 = $request->input('SAMLResponse');
        if (! $b64) {
            Log::info('Missing SAMLResponse');

            return $this->samlErrorResponse(400, 'SAMLResponse mancante');
        }

        $xml = base64_decode($b64, true);
        if ($xml === false || stripos($xml, '<!DOCTYPE') !== false) {
            Log::info('Invalid base64 SAMLResponse');

            return $this->samlErrorResponse(400, 'SAMLResponse non valida');
        }

        // Where trust comes from:
        // - simulation route (auth.sim.saml, behind shield:auth_sim): the operator
        //   is already authenticated -> no signature;
        // - any other route: the IdP SIGNATURE, verified against the pinned certificate.
        // What decides is WHICH route matched, never request data -> fail-closed.
        $isSimulation = $request->route()?->named('auth.sim.saml') ?? false;

        if ($isSimulation) {
            libxml_use_internal_errors(true);
            $doc = new \DOMDocument;
            if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                return $this->samlErrorResponse(400, 'SAMLResponse non valida');
            }
            $signedNodes = [$doc->documentElement];
        } else {
            try {
                $signedNodes = app(SamlSignatureVerifier::class)->verifiedNodes($xml);
            } catch (\Throwable $e) {
                Log::warning('SAML signature verification failed', [
                    'message' => $e->getMessage(),
                    'ip' => $request->ip(),
                ]);

                return $this->samlErrorResponse(401, 'Firma SAMLResponse non valida');
            }
        }

        // Attributes are read ONLY from the nodes covered by the signature (anti-XSW),
        // never with a global XPath.
        $attrs = $this->extractSamlAttributes($signedNodes);
        $username = trim((string) ($attrs[config('saml.username_attribute', 'username')] ?? ''));

        if ($username === '') {
            Log::info('SAML username attribute not found');

            return $this->samlErrorResponse(401, 'Username non trovato nella SAMLResponse');
        }

        $user = User::query()->find($username);

        if (! $user) {
            Log::warning('Auth failed: user not found', ['username' => $username, 'ip' => $request->ip()]);

            return $this->redirectToFrontend($this->apiTokenCookie(''));
        }

        // SSO users log in with the HKDF-derived username as password: the argon2id
        // driver (DerivingArgon2IdHasher) applies the derivation internally, so both
        // the auto-heal and the check below work on the raw username.
        if (empty($user->password)) {
            $user->password = Hash::make($user->getKey());
            $user->save();
            Log::info('Password auto-generated during SAML auth', ['username' => $user->getKey()]);
        }

        if (! Hash::check($user->getKey(), $user->password)) {
            Log::critical('SAML/password mismatch — possible security anomaly', [
                'username' => $user->getKey(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'timestamp' => now()->toIso8601String(),
            ]);

            return $this->redirectToFrontend($this->apiTokenCookie(''));
        }

        Auth::login($user);
        Log::info('Auth successful', [
            'username' => $user->getKey(),
            'role' => $user->role?->name,
        ]);

        // Token expires after session lifetime; cookie is always session cookie (cleared when browser closes)
        $sessionLifetime = (int) config('session.lifetime', 20);
        $expiresAt = $sessionLifetime > 0 ? now()->addMinutes($sessionLifetime) : null;

        $plainText = $user->createToken('api-token', ['*'], $expiresAt)->plainTextToken;
        $apiToken = explode('|', $plainText, 2)[1];

        return $this->redirectToFrontend($this->apiTokenCookie($apiToken));
    }

    /**
     * Minimal SAML 2.0 Response for the simulation: issuer, subject and the
     * username attribute (config/saml.php). Unsigned on purpose: it is only
     * ever posted to the simulation route, which skips the signature check.
     */
    private function buildSamlResponse(string $username): string
    {
        $now = now()->utc();
        $issued = $now->format('Y-m-d\TH:i:s.v\Z');
        $notOnOrAfter = $now->copy()->addMinutes(5)->format('Y-m-d\TH:i:s.v\Z');
        $responseId = '_'.Str::uuid();
        $assertionId = '_'.Str::uuid();

        $issuer = htmlspecialchars((string) config('saml.idp_entity_id'), ENT_XML1);
        $audience = htmlspecialchars((string) config('saml.sp_entity_id'), ENT_XML1);
        $attribute = htmlspecialchars((string) config('saml.username_attribute', 'username'), ENT_XML1);
        $value = htmlspecialchars($username, ENT_XML1);

        return <<<XML
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="{$responseId}" Version="2.0" IssueInstant="{$issued}" Destination="{$audience}">
<saml:Issuer>{$issuer}</saml:Issuer>
<samlp:Status>
	<samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
</samlp:Status>
<saml:Assertion ID="{$assertionId}" IssueInstant="{$issued}" Version="2.0">
	<saml:Issuer>{$issuer}</saml:Issuer>
	<saml:Subject>
	<saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">{$value}</saml:NameID>
	<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
		<saml:SubjectConfirmationData NotOnOrAfter="{$notOnOrAfter}" Recipient="{$audience}"/>
	</saml:SubjectConfirmation>
	</saml:Subject>
	<saml:Conditions NotBefore="{$issued}" NotOnOrAfter="{$notOnOrAfter}">
	<saml:AudienceRestriction>
		<saml:Audience>{$audience}</saml:Audience>
	</saml:AudienceRestriction>
	</saml:Conditions>
	<saml:AttributeStatement>
	<saml:Attribute Name="{$attribute}">
		<saml:AttributeValue>{$value}</saml:AttributeValue>
	</saml:Attribute>
	</saml:AttributeStatement>
	<saml:AuthnStatement AuthnInstant="{$issued}" SessionIndex="{$assertionId}">
	<saml:AuthnContext>
		<saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
	</saml:AuthnContext>
	</saml:AuthnStatement>
</saml:Assertion>
</samlp:Response>
XML;
    }

    /** Consistent XML error response of the SAML endpoint. */
    private function samlErrorResponse(int $code, string $message): Response
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<error><code>'.$code.'</code><message>'.htmlspecialchars($message, ENT_XML1).'</message></error>';

        return response($xml, $code)->header('Content-Type', 'application/xml');
    }

    /**
     * The apiToken cookie the frontend reads (httpOnly off on purpose) and
     * sends back as Bearer. An empty value clears it.
     */
    private function apiTokenCookie(string $value): Cookie
    {
        return cookie(
            name: 'apiToken',
            value: $value,
            minutes: 0,
            path: '/',
            domain: config('session.domain'),
            secure: parse_url(config('app.url'), PHP_URL_SCHEME) === 'https',
            httpOnly: false,
            raw: false,
            sameSite: 'lax'
        );
    }

    private function redirectToFrontend(Cookie $cookie): RedirectResponse
    {
        return redirect()
            ->away(rtrim(config('app.frontend_url'), '/'))
            ->withCookie($cookie);
    }

    /**
     * Read SAML attributes ONLY from the nodes covered by the signature (anti-XSW):
     * never with a global XPath.
     *
     * @param  list<\DOMNode>  $signedNodes
     * @return array<string, string>
     */
    private function extractSamlAttributes(array $signedNodes): array
    {
        $attrs = [];
        foreach ($signedNodes as $node) {
            $doc = $node instanceof \DOMDocument ? $node : $node->ownerDocument;
            $xp = new \DOMXPath($doc);
            $xp->registerNamespace('saml', 'urn:oasis:names:tc:SAML:2.0:assertion');

            foreach ($xp->query('.//saml:AttributeStatement/saml:Attribute', $node) as $attr) {
                $name = $attr->getAttribute('Name');
                $valNode = $xp->query('./saml:AttributeValue', $attr)->item(0);
                $val = $valNode ? trim($valNode->textContent) : null;
                if ($name !== '' && $val !== null) {
                    $attrs[$name] = $val;
                }
            }
        }

        return $attrs;
    }
}
