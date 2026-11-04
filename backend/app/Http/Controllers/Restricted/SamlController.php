<?php

namespace App\Http\Controllers\Restricted;

use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\SamlSignatureVerifier;
use App\Services\Auth\SamlUserAllowlist;
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
use Illuminate\View\View;

/**
 * Auth Simulation: permette di simulare un login scegliendo l'utenza (scenari/banche) e il
 * protocollo — SAML (SAMLResponse costruita qui e auto-postata all'endpoint vero) oppure OIDC
 * (l'utenza va in sessione e parte il flusso reale, vedi NexiOidcController).
 *
 * Contiene anche `authSaml()`, che NON è simulazione: è l'endpoint SAML vero.
 */
class SamlController extends Controller
{
	private const PROTOCOL_SAML = "saml";

	private const PROTOCOL_OIDC = "oidc";

	public function __construct(
		protected SamlSimulationScenarioService $scenarios,
		protected SamlSimulationLogService $simulationLog
	) {}

	#[ExcludeRouteFromDocs]
	public function form(Request $request): View
	{
		$banks = $this->scenarios->banksForSelect();
		$selectedBancaAbi = $this->resolveSelectedBancaAbi($request->query("banca_abi"));
		$availableBancaAbis = array_column($banks, "banca_abi");

		if ($selectedBancaAbi !== null && !in_array($selectedBancaAbi, $availableBancaAbis, true)) {
			$selectedBancaAbi = null;
		}

		$catalog = $this->scenarios->catalog($selectedBancaAbi);

		$baseScenarios = [];
		$productScenarios = [];
		foreach ($catalog as $key => $item) {
			if ($this->scenarios->isProductScenario($key)) {
				$productScenarios[] = $item;
			} else {
				$baseScenarios[] = $item;
			}
		}

		// Merge base + product scenarios into a single list.
		$scenarios = [...$baseScenarios, ...$productScenarios];

		$selectedBank = $selectedBancaAbi !== null ? collect($banks)->firstWhere("banca_abi", $selectedBancaAbi) : null;

		// Determine which product groups are visible based on the selected bank.
		// When "all banks" is selected, show all groups.
		$visibleProductGroups = $this->visibleProductGroups($selectedBank);

		// Group banks by product configuration for optgroup labels.
		$bankGroups = $this->groupBanksForSelect($banks);

		return view("auth-simulation.index", [
			"postAction" => route("auth.sim.post"),
			"relay" => (string) $request->query("relay", ""),
			"banks" => $banks,
			"bankGroups" => $bankGroups,
			"selectedBancaAbi" => $selectedBancaAbi,
			"selectedBank" => $selectedBank,
			"scenarios" => array_values($scenarios),
			"productScenarioKeys" => array_keys(SamlSimulationScenarioService::PRODUCT_SCENARIOS),
			"visibleProductGroups" => $visibleProductGroups,
			"errorScenarios" => array_values($this->scenarios->errorCatalog($selectedBancaAbi)),
			"logs" => $this->simulationLog->paginate($request),
			"operator" => $this->scenarios->operatorContext(),
			"protocol" => $this->resolveProtocol($request->query("protocol")),
			"oidcAvailable" => config("app.tenant") === "nexi-multi",
		]);
	}

	/**
	 * Determine which product scenario groups are visible based on the selected bank.
	 * Returns all groups when no specific bank is selected ("all banks").
	 *
	 * @param  array{banca_abi: string, nome: string, multi_tiraggio: bool, conguaglio_periodico: bool, metodo_conguaglio: ?string, durata_parametrica: bool}|null  $bank
	 * @return list<string>
	 */
	private function visibleProductGroups(?array $bank): array
	{
		if ($bank === null) {
			return ["multi", "durata_parametrica", "multi_durata_parametrica"];
		}

		if ($bank["multi_tiraggio"] && $bank["durata_parametrica"]) {
			return ["multi_durata_parametrica"];
		}

		if ($bank["multi_tiraggio"]) {
			return ["multi"];
		}

		if ($bank["durata_parametrica"]) {
			return ["durata_parametrica"];
		}

		return [];
	}

	/**
	 * Group banks by product configuration for optgroup labels in the select.
	 *
	 * @param  list<array{banca_abi: string, nome: string, multi_tiraggio: bool, conguaglio_periodico: bool, metodo_conguaglio: ?string, durata_parametrica: bool}>  $banks
	 * @return array<string, list<array{banca_abi: string, nome: string}>>
	 */
	private function groupBanksForSelect(array $banks): array
	{
		$groups = [];

		foreach ($banks as $bank) {
			if ($bank["multi_tiraggio"] && $bank["durata_parametrica"]) {
				$key = "multi_durata_parametrica";
			} elseif ($bank["multi_tiraggio"]) {
				$key = "multi_tiraggio";
			} elseif (
				$bank["durata_parametrica"] &&
				$bank["conguaglio_periodico"] &&
				$bank["metodo_conguaglio"] === "previsionale"
			) {
				$key = "durata_parametrica_previsionale";
			} elseif (
				$bank["durata_parametrica"] &&
				$bank["conguaglio_periodico"] &&
				$bank["metodo_conguaglio"] === "plafond"
			) {
				$key = "durata_parametrica_plafond";
			} elseif ($bank["durata_parametrica"]) {
				$key = "durata_parametrica_senza_conguaglio";
			} else {
				$key = "standard";
			}

			$groups[$key][] = [
				"banca_abi" => $bank["banca_abi"],
				"nome" => $bank["nome"],
			];
		}

		$labels = [
			"standard" => "Standard",
			"multi_tiraggio" => "Multi-Tiraggio",
			"durata_parametrica_previsionale" => "Durata parametrica · Conguaglio previsionale",
			"durata_parametrica_plafond" => "Durata parametrica · Conguaglio plafond",
			"durata_parametrica_senza_conguaglio" => "Durata parametrica · Senza conguaglio",
			"multi_durata_parametrica" => "Multi-Tiraggio + Durata parametrica",
		];

		$ordered = [];
		foreach ($labels as $key => $label) {
			if (isset($groups[$key])) {
				$ordered[$label] = $groups[$key];
			}
		}

		return $ordered;
	}

	#[ExcludeRouteFromDocs]
	public function autoPost(Request $request): View|RedirectResponse
	{
		$validated = $request->validate([
			"access_token" => ["nullable", "string"],
			"idfisico" => ["nullable", "string"],
			"idgiuridico" => ["nullable", "string"],
			"filiale_madre" => ["nullable", "string"],
			"name_id" => ["nullable", "string"],
			"RelayState" => ["nullable", "string"],
			"scenario" => ["nullable", "string"],
			"banca_abi" => ["nullable", "string"],
			"protocol" => ["nullable", "string"],
		]);

		$protocol = $this->resolveProtocol($validated["protocol"] ?? null);

		$scenario = (string) ($validated["scenario"] ?? SamlSimulationScenarioService::SCENARIO_MANUAL);
		if (!$this->scenarios->isKnownScenario($scenario)) {
			$scenario = SamlSimulationScenarioService::SCENARIO_MANUAL;
		}

		$accessToken = trim((string) ($validated["access_token"] ?? ""));
		$idfisico = trim((string) ($validated["idfisico"] ?? ""));
		$idgiuridico = trim((string) ($validated["idgiuridico"] ?? ""));
		$filialeMadre = trim((string) ($validated["filiale_madre"] ?? ""));
		$nameId = trim((string) ($validated["name_id"] ?? "e65547ec-286c-433c-b65d-b2ca596e2db2"));
		$relayState = $validated["RelayState"] ?? null;
		$selectedBancaAbi = $this->resolveSelectedBancaAbi($validated["banca_abi"] ?? null);

		if ($selectedBancaAbi !== null && !$this->scenarios->isKnownBancaAbi($selectedBancaAbi)) {
			return redirect()
				->route(
					"auth.sim.form",
					array_filter([
						"relay" => $relayState,
						"banca_abi" => $selectedBancaAbi,
					])
				)
				->withErrors([
					"banca_abi" => "Banca selezionata non valida.",
				]);
		}

		if ($scenario !== SamlSimulationScenarioService::SCENARIO_MANUAL) {
			$randomUserId = $this->scenarios->randomUserIdForScenario($scenario, $selectedBancaAbi);
			if ($randomUserId === null) {
				$meseMancante = $this->scenarios->meseStoricoMancante($scenario, $selectedBancaAbi);

				return redirect()
					->route(
						"auth.sim.form",
						array_filter([
							"relay" => $relayState,
							"banca_abi" => $selectedBancaAbi,
						])
					)
					->withErrors([
						"scenario" =>
							$meseMancante !== null
								? "Nessun utente disponibile: lo storico dei clienti non arriva a {$meseMancante}, richiesto dalla piattaforma. Rilancia app:init per riallinearlo."
								: "Nessun utente disponibile per lo scenario e la banca selezionati.",
					]);
			}

			$idgiuridico = $randomUserId;
		}

		if ($idgiuridico === "") {
			return redirect()
				->route("auth.sim.form", array_filter(["relay" => $relayState]))
				->withInput()
				->withErrors([
					"idgiuridico" => "Inserisci un User ID.",
				]);
		}

		$operator = $this->scenarios->operatorContext();
		$this->simulationLog->append([
			"simulated_user_id" => $idgiuridico,
			"scenario" => $scenario,
			"scenario_label" => $this->scenarios->labelFor($scenario),
			"operator_user_id" => $operator["user_id"] ?? null,
			"operator_email" => $operator["email"] ?? null,
			"operator_role" => $operator["role"] ?? null,
		]);

		Log::info("Auth simulation started", [
			"protocol" => $protocol,
			"scenario" => $scenario,
			"simulated_user_id" => $idgiuridico,
			"operator_email" => $operator["email"] ?? null,
		]);

		// OIDC: l'utenza è scelta, il resto lo fa il flusso reale. Il valore in sessione dice
		// solo *quale* utenza ha scelto l'operatore — non è un marcatore di fiducia: a decidere
		// che il Provider siamo noi resta la route (`oidc.sim.*`), raggiungibile solo da qui.
		if ($protocol === self::PROTOCOL_OIDC) {
			session(["oidc_sim_user_id" => $idgiuridico]);

			return redirect()->route("oidc.sim.request", ["external_id" => $idgiuridico]);
		}

		$xml = <<<'XML'
<samlp:Response xmlns:samlp="urn:oasis:names:tc:SAML:2.0:protocol" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion" ID="_8f88d41d-b242-482e-a871-cb34c89fdab2" Version="2.0" IssueInstant="2024-07-03T09:31:27.817Z" Destination="https://anticipotransatopos.bancodesio.it/" InResponseTo="_29931b89-b513-422d-ad14-51a3dc52f18b">
<saml:Issuer>https://psd.bancodesio.it/identificationapi/IdpSaml/sso</saml:Issuer>
<Signature xmlns="http://www.w3.org/2000/09/xmldsig#">
	<SignedInfo>
	<CanonicalizationMethod Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
	<SignatureMethod Algorithm="http://www.w3.org/2001/04/xmldsig-more#rsa-sha256"/>
	<Reference URI="#_8f88d41d-b242-482e-a871-cb34c89fdab2">
		<Transforms>
		<Transform Algorithm="http://www.w3.org/2000/09/xmldsig#enveloped-signature"/>
		<Transform Algorithm="http://www.w3.org/2001/10/xml-exc-c14n#"/>
		</Transforms>
		<DigestMethod Algorithm="http://www.w3.org/2001/04/xmlenc#sha256"/>
		<DigestValue>yvNKUuw+iMmBMs3rSlOzGRFIUW5av7W7/N+2LLP5U6M=</DigestValue>
	</Reference>
	</SignedInfo>
	<SignatureValue>lAVPUPmwUvVPnlKZb4fiROXgPynzB5EYj6saXsjHaH8Nh5pvUj70FsxXUcb7pkadhV6T5NkPUzk6gPx55S8dyJuicWD2mdHRErdhddVewe2ZAZCZ7935tc8PKi/InWcPsNZqTH8eMo38Xlz8Juq9E/JWa1PxA4ZSH7XXK07ZIfj+4dC6NMHuRYVD77gq09vw4djL4m+kqdZaZ9Q0R6jiM6dp0Ego5wvLl3xYMA4/pe3UXpVn4yN1KJrcolJNABQeV+w56Nj9BJKrL9d6NSU/ZDqiyTxFSPRtN1+w07I+4oWGWOCMs8F0ycQ+lEKfVJ0TkW6RL9R4Hps2rETvWROX/JpT79YSuub5jQhiHbz/XrO0Y+wbrWqo7eVlno/dy5IQBxTvdSlSUJckzmSkzfYWu4MYD47Ww0MAfG99rzDV7j+ln/26W2xWxA1iX7tOCiLUst+YdsG7q3iIpygbgyMJ19cIUPtlAoxwAULGhGIKVeHWpkHJCGx9e0gYbgkqr2kKgpxvzZ9bxNxwcAUQ5Hce+mFJFy5GmCpbh8/vYDCqx4Sc/5imYliSCEHFTP4I03+f/hZAAkLGO1hQCkbdAjfaR4fvxLzjO3YTHgMKtqGZfJyVR4zjutE1SlNDepDqe82LgweNPZXs/6GVu7fdf110fw5wWZMmYDFXH/WldYpcCII=</SignatureValue>
	<KeyInfo>
	<X509Data>
		<X509Certificate>MIIFqDCCA5CgAwIBAgIJAI0QzRUi6L7fMA0GCSqGSIb3DQEBCwUAMGkxFjAUBgNVBAMMDWJhbmNvZGVzaW8uaXQxDDAKBgNVBAsMA1NTTzEkMCIGA1UECgwbQmFuY28gRGVzaW8gZSBkZWxsYSBCcmlhbnphMQ4wDAYDVQQHDAVEZXNpbzELMAkGA1UEBhMCSVQwHhcNMjQwMjAyMTIwNjIyWhcNMzQwMTMwMTIwNjIyWjBpMRYwFAYDVQQDDA1iYW5jb2Rlc2lvLml0MQwwCgYDVQQLDANTU08xJDAiBgNVBAoMG0JhbmNvIERlc2lvIGUgZGVsbGEgQnJpYW56YTEOMAwGA1UEBwwFRGVzaW8xCzAJBgNVBAYTAklUMIICIjANBgkqhkiG9w0BAQEFAAOCAg8AMIICCgKCAgEA2LeoRI2u9L32v8CZhlvEMO2SzEazMjFaSIHrRJjz8zCFvYcbnuN2Sb87QYt4YmVGSvUjtaTqR1F/ZeUslbq1c0AdlS3hGGKH5fuhTbxtInlRbqIjih352DJHsBF7vLnCQHO8POiL2aPGk+tOk2gJt8u5pRPuxeFnyKYmYP64iIOICRmLi81J097EjtnTWweiO/REhDi4z6HSBF1GouHAZdVAZW6GTdQF4sThA9dbC2PD+s1NvGrpWrmcQVHD20a4VTfNzwPUlOWYERL4oL+hzeEEnF3Y7vD5fPHx8kj1TTn0R/ROGUxeqAFVu+LlkQAwonlP5kIsLx94OfaYj/lzr6xTP6EsnJMneIz1vJtw/Utk7OFCDmW26yJ5jz30n4zloeOoRSNQ68M8xdkG1pamT4gbyzbGQTWxU8SKapxr2Y5/+CMOVv+5kvo+Y5HUumM5bywEDKBArNVcbIWp3DCng4Y+mlP736pHQ1PZsutk/VpavHi2V++J4hIC4gdOaK0Jb75GmbKReYKpB4dxBt2GRshV6+iZWt1QMiV49pMjS33LJBxyqL3InW5ZyCzNRWjkz8llZBVuiY0XiCdZFYyAHeFkOIyWVwazSBBcI7ckc47V/LuRCp+vFYCjvwShocVLNgVJu6AJxqHjm58oM0TIKMOuJaJvuIiTyenwsz3euV0CAwEAAaNTMFEwHQYDVR0OBBYEFLTZXv6dv69yIsb6z2oZuxKNSFXLMB8GA1UdIwQYMBaAFLTZXv6dv69yIsb6z2oZuxKNSFXLMA8GA1UdEwEB/wQFMAMBAf8wDQYJKoZIhvcNAQELBQADggIBAGNITvmu3cmBfcp7KTfTrCX7MOWLLoFklvrtzPxrxnsVtzEeHvKQvOwG5kgryKi4lnDn/QmgGN71uC9tZlP23kFTjqvR3IyEkHLnL7fpOthJ6b69XPbw9/9z28bl7G2IWk9U+TgLG509svpXg0+iK5CM/4CT+8Sv1QUxBBeoJHs1oI8CSaAmbV7GJm8r0pNwCnUNmkGeLgBC6cDXzo2rddTGczFzSgEA2DypGrSWOXTjuM7Tdz6ioHxmHSfCd+usHPK0vBPh80v9CM1EoHVNNxOMESJVb3dzsbgwYlGVFh3T1rObW96EwB+cTgd96ZCsGjs5NTaFLPGjgrb1IV+0D2ODghqDwNQx4mtFfuBZG6g4do1mSO8YYa9Nh1avKBj8zp3azdvXcTJVO+bu4KFYSsJ+3dSa5GEJNtb+SHRUfLpRSmbpesLLtds/LHd54eASh6/8nA8kR6g8fsQg23N6OBYDVB5GOXIzzxXjQGnJ5gBzHtSxDoJH+MEOGhbQ73ezOio4cZEG0Zp8z6C6HzUfRjy2i/BPV7c/9PWwcuOUiimvfKK6PzXqM3OhlTx7ovSCR+3m7OmnmGU0rOqID13tZhoC6TkaabneOliAgN5w3XPY+8ySu394ajiPva2KNYTnxizVW6X+4yiJo5ePs6CBeCaaurXT3/L0Umz4FWwo4WUd</X509Certificate>
	</X509Data>
	</KeyInfo>
</Signature>
<samlp:Status>
	<samlp:StatusCode Value="urn:oasis:names:tc:SAML:2.0:status:Success"/>
</samlp:Status>
<saml:Assertion ID="_79cf4690-add9-4275-b3d7-18522fa57af3" IssueInstant="2024-07-03T09:31:27.817Z" Version="2.0" xmlns:saml="urn:oasis:names:tc:SAML:2.0:assertion">
	<saml:Issuer>https://psd.bancodesio.it/identificationapi/IdpSaml/sso</saml:Issuer>
	<saml:Subject>
	<saml:NameID Format="urn:oasis:names:tc:SAML:2.0:nameid-format:persistent">{{NAME_ID}}</saml:NameID>
	<saml:SubjectConfirmation Method="urn:oasis:names:tc:SAML:2.0:cm:bearer">
		<saml:SubjectConfirmationData InResponseTo="_29931b89-b513-422d-ad14-51a3dc52f18b" NotOnOrAfter="2024-07-03T09:36:27.817Z" Recipient="https://anticipotransatopos.bancodesio.it"/>
	</saml:SubjectConfirmation>
	</saml:Subject>
	<saml:Conditions NotBefore="2024-07-03T09:31:27.817Z" NotOnOrAfter="2024-07-03T10:31:27.817Z">
	<saml:AudienceRestriction>
		<saml:Audience>https://anticipotransatopos.bancodesio.it</saml:Audience>
	</saml:AudienceRestriction>
	</saml:Conditions>
	<saml:AttributeStatement>
	<saml:Attribute Name="id">
		<saml:AttributeValue>{{NAME_ID}}</saml:AttributeValue>
	</saml:Attribute>
	<saml:Attribute Name="access-token">
		<saml:AttributeValue>{{ACCESS_TOKEN}}</saml:AttributeValue>
	</saml:Attribute>
	<saml:Attribute Name="user_id-persona-fisica">
		<saml:AttributeValue>{{USER_ID_FISICO}}</saml:AttributeValue>
	</saml:Attribute>
	<saml:Attribute Name="user_ids-aziende">
		<saml:AttributeValue>{{USER_ID_GIURIDICO}}</saml:AttributeValue>
	</saml:Attribute>
	<saml:Attribute Name="filiale-madre-persona-giuridica">
		<saml:AttributeValue>{{FILIALE_MADRE}}</saml:AttributeValue>
	</saml:Attribute>
	</saml:AttributeStatement>
	<saml:AuthnStatement AuthnInstant="2024-07-03T09:31:27.817Z" SessionIndex="18924b1b-e587-4840-bf12-d92cbb8654a7">
	<saml:AuthnContext>
		<saml:AuthnContextClassRef>urn:oasis:names:tc:SAML:2.0:ac:classes:PasswordProtectedTransport</saml:AuthnContextClassRef>
	</saml:AuthnContext>
	</saml:AuthnStatement>
</saml:Assertion>
</samlp:Response>
XML;

		$xml = str_replace(
			["{{ACCESS_TOKEN}}", "{{USER_ID_FISICO}}", "{{USER_ID_GIURIDICO}}", "{{FILIALE_MADRE}}", "{{NAME_ID}}"],
			[
				htmlspecialchars($accessToken, ENT_XML1),
				htmlspecialchars($idfisico, ENT_XML1),
				htmlspecialchars($idgiuridico, ENT_XML1),
				htmlspecialchars($filialeMadre, ENT_XML1),
				htmlspecialchars($nameId, ENT_XML1),
			],
			$xml
		);

		$b64 = base64_encode($xml);
		// Il simulatore posta alla route GEMELLA (dietro shield operatore), non all'endpoint
		// reale: quello ora pretende una firma valida, che il simulatore non può produrre.
		$acs = route("auth.sim.saml");

		return view("auth-simulation.posting", [
			"acs" => $acs,
			"samlResponse" => $b64,
			"relayState" => $relayState,
			"simulatedUserId" => $idgiuridico,
		]);
	}

	#[ExcludeRouteFromDocs]
	public function authSaml(Request $request): RedirectResponse|Response
	{
		$frontendUrl = rtrim(config("app.frontend_url"), "/");
		$domain = config("session.domain");
		$appUrl = config("app.url");
		$scheme = parse_url($appUrl, PHP_URL_SCHEME);

		$b64 = $request->input("SAMLResponse");
		if (!$b64) {
			Log::info("Missing SAMLResponse");
			Log::info($b64);
			$xmlError = '<?xml version="1.0" encoding="UTF-8"?>
			<error>
				<code>400</code>
				<message>SAMLResponse mancante</message>
			</error>';

			return response($xmlError, 400)->header("Content-Type", "application/xml");
		}

		$xml = base64_decode($b64, true);
		if ($xml === false || stripos($xml, "<!DOCTYPE") !== false) {
			Log::info("Invalid base64 SAMLResponse");

			return response($this->samlError(400, "SAMLResponse non valida"), 400)->header(
				"Content-Type",
				"application/xml"
			);
		}

		// Da dove viene la fiducia:
		// - route sim (auth.sim.saml, dietro shield:auth_sim): operatore gia' autenticato -> niente firma;
		// - route reale (auth.saml, pubblica): dalla FIRMA dell'IdP, verificata contro il cert pinnato.
		// La discriminante e' QUALE route ha fatto match, non un dato della request -> fail-closed.
		$isSimulation = $request->route()?->named("auth.sim.saml") ?? false;

		if ($isSimulation) {
			libxml_use_internal_errors(true);
			$doc = new \DOMDocument();
			if (!$doc->loadXML($xml, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
				return response($this->samlError(400, "SAMLResponse non valida"), 400)->header(
					"Content-Type",
					"application/xml"
				);
			}
			$signedNodes = [$doc->documentElement];
		} else {
			try {
				$signedNodes = app(SamlSignatureVerifier::class)->verifiedNodes($xml);
			} catch (\Throwable $e) {
				Log::warning("SAML signature verification failed", [
					"message" => $e->getMessage(),
					"ip" => $request->ip(),
				]);

				return response($this->samlError(401, "Firma SAMLResponse non valida"), 401)->header(
					"Content-Type",
					"application/xml"
				);
			}
		}

		// Attributi letti SOLO dai nodi coperti dalla firma (anti-XSW), mai con XPath globale.
		$attrs = $this->extractSamlAttributes($signedNodes);

		$jwt = $attrs["access-token"] ?? null;
		$idfisico = $attrs["user_id-persona-fisica"] ?? null;
		$idgiuridico = $attrs["user_ids-aziende"] ?? null;
		$nomegiuridico = $attrs["ragione-sociale-persona-giuridica"] ?? null;

		if (!$jwt) {
			Log::info("JWT not found");
			Log::info($jwt);
			$xmlError = '<?xml version="1.0" encoding="UTF-8"?>
			<error>
				<code>401</code>
				<message>JWT non trovato</message>
			</error>';

			return response($xmlError, 401)->header("Content-Type", "application/xml");
		}

		if (!$idfisico && !$idgiuridico) {
			Log::info("No IDFISICO or IDGIURIDICO found");
			$xmlError = '<?xml version="1.0" encoding="UTF-8"?>
			<error>
				<code>401</code>
				<message>No IDFISICO or IDGIURIDICO found</message>
			</error>';

			return response($xmlError, 401)->header("Content-Type", "application/xml");
		}

		Log::info("JWT: {$jwt}");
		Log::info("IDFISICO: {$idfisico}");
		Log::info("IDGIURIDICO: {$idgiuridico}");
		Log::info("RAGIONE SOCIALE: {$nomegiuridico}");

		// Resolve user from identifier(s). Expected format: banca_abi-ndg (e.g. 73801-2755069).
		$user = null;
		if ($idgiuridico && strpos($idgiuridico, ",") !== false) {
			$identifiers = array_filter(array_map("trim", explode(",", $idgiuridico)));
			$userIdList = [];
			$ragioneSocialeList = [];
			foreach ($identifiers as $userId) {
				$found = User::where("user_id", $userId)->where("sospeso", false)->first();
				if ($found) {
					$userIdList[] = $found->user_id;
					$ragioneSocialeList[] = $found->ragione_sociale ?? "N/A";
				}
			}
			if (count($userIdList) > 1) {
				return response()->view("saml.select_user_id", [
					"userIdList" => $userIdList,
					"ragioneSocialeList" => $ragioneSocialeList,
					// Allowlist firmata: la scelta successiva è accettata solo se in questa lista.
					"allowlist" => app(SamlUserAllowlist::class)->sign($userIdList),
				]);
			}
			if (count($userIdList) === 1) {
				$user = User::where("user_id", $userIdList[0])->where("sospeso", false)->first();
			}
		} else {
			$userId = $idgiuridico !== "" && $idgiuridico !== null ? $idgiuridico : $idfisico;
			if ($userId !== "" && $userId !== null) {
				$user = User::where("user_id", $userId)->where("sospeso", false)->first();
			}
		}

		if (!$user) {
			Log::warning("Auth failed: user not found or suspended", [
				"idgiuridico" => $idgiuridico,
				"idfisico" => $idfisico,
				"ip" => $request->ip(),
			]);

			$cookie = cookie(
				name: "apiToken",
				value: "",
				minutes: 0,
				path: "/",
				domain: $domain,
				secure: $scheme === "https",
				httpOnly: false,
				raw: false,
				sameSite: "lax"
			);

			return redirect()
				->away("{$frontendUrl}")
				->withCookie($cookie);
		}

		// The user's password is the HKDF-derived user_id; the argon2id driver
		// (DerivingArgon2IdHasher) applies the derivation internally, so both the
		// auto-heal and the check below work on the raw user_id.
		if (empty($user->password)) {
			$user->password = Hash::make($user->user_id);
			$user->save();
			Log::info("Password auto-generated during SAML auth", ["user_id" => $user->user_id]);
		}

		if (!Hash::check($user->user_id, $user->password)) {
			Log::critical("SAML/password mismatch — possible security anomaly", [
				"user_id" => $user->user_id,
				"ip" => $request->ip(),
				"user_agent" => $request->userAgent(),
				"timestamp" => now()->toIso8601String(),
			]);

			$cookie = cookie(
				name: "apiToken",
				value: "",
				minutes: 0,
				path: "/",
				domain: $domain,
				secure: $scheme === "https",
				httpOnly: false,
				raw: false,
				sameSite: "lax"
			);

			return redirect()
				->away("{$frontendUrl}")
				->withCookie($cookie);
		}

		Auth::login($user);
		Log::info("Auth successful", [
			"user_id" => $user->user_id,
			...$this->situazionePiattaforma($user),
		]);

		// Token expires after session lifetime; cookie is always session cookie (cleared when browser closes)
		$sessionLifetime = (int) config("session.lifetime", 20);
		$expiresAt = $sessionLifetime > 0 ? now()->addMinutes($sessionLifetime) : null;

		$plainText = $user->createToken("api-token", ["*"], $expiresAt)->plainTextToken;
		$apiToken = explode("|", $plainText, 2)[1];

		$cookie = cookie(
			name: "apiToken",
			value: $apiToken,
			minutes: 0,
			path: "/",
			domain: $domain,
			secure: $scheme === "https",
			httpOnly: false,
			raw: false,
			sameSite: "lax"
		);

		return redirect()
			->away("{$frontendUrl}")
			->withCookie($cookie);
	}

	#[ExcludeRouteFromDocs]
	public function authSamlSelectUserId(Request $request): RedirectResponse|Response
	{
		$frontendUrl = rtrim(config("app.frontend_url"), "/");
		$domain = config("session.domain");
		$appUrl = config("app.url");
		$scheme = parse_url($appUrl, PHP_URL_SCHEME);

		$userId = trim((string) $request->input("idgiuridico", ""));

		// La scelta è accettata solo se compresa nell'allowlist firmata emessa da authSaml
		// (che a monte ha verificato la firma dell'IdP). Senza, questo endpoint sarebbe un
		// bypass: chiunque potrebbe postare un idgiuridico e loggarsi.
		$allowlist = (string) $request->input("allowlist", "");
		if (!app(SamlUserAllowlist::class)->contains($allowlist, $userId)) {
			Log::warning("SAML select-user-id: allowlist verification failed", [
				"idgiuridico" => $userId,
				"ip" => $request->ip(),
			]);

			return response($this->samlError(403, "Selezione non autorizzata"), 403)->header(
				"Content-Type",
				"application/xml"
			);
		}

		Log::info("SELECTED IDGIURIDICO: {$userId}");

		$user = User::where("user_id", $userId)->where("sospeso", false)->first();

		if (!$user) {
			Log::warning("Auth failed: user not found or suspended", [
				"idgiuridico" => $userId,
				"ip" => $request->ip(),
			]);

			$cookie = cookie(
				name: "apiToken",
				value: "",
				minutes: 0,
				path: "/",
				domain: $domain,
				secure: $scheme === "https",
				httpOnly: false,
				raw: false,
				sameSite: "lax"
			);

			return redirect()
				->away("{$frontendUrl}")
				->withCookie($cookie);
		}

		// See authSaml: the derivation-aware hasher works on the raw user_id.
		if (empty($user->password)) {
			$user->password = Hash::make($user->user_id);
			$user->save();
			Log::info("Password auto-generated during SAML auth (select user)", ["user_id" => $user->user_id]);
		}

		if (!Hash::check($user->user_id, $user->password)) {
			Log::critical("SAML/password mismatch — possible security anomaly", [
				"user_id" => $user->user_id,
				"ip" => $request->ip(),
				"user_agent" => $request->userAgent(),
				"timestamp" => now()->toIso8601String(),
			]);

			$cookie = cookie(
				name: "apiToken",
				value: "",
				minutes: 0,
				path: "/",
				domain: $domain,
				secure: $scheme === "https",
				httpOnly: false,
				raw: false,
				sameSite: "lax"
			);

			return redirect()
				->away("{$frontendUrl}")
				->withCookie($cookie);
		}

		Auth::login($user);
		Log::info("Auth successful", [
			"user_id" => $user->user_id,
			...$this->situazionePiattaforma($user),
		]);

		// Token expires after session lifetime; cookie is always session cookie (cleared when browser closes)
		$sessionLifetime = (int) config("session.lifetime", 20);
		$expiresAt = $sessionLifetime > 0 ? now()->addMinutes($sessionLifetime) : null;

		$plainText = $user->createToken("api-token", ["*"], $expiresAt)->plainTextToken;
		$apiToken = explode("|", $plainText, 2)[1];

		$cookie = cookie(
			name: "apiToken",
			value: $apiToken,
			minutes: 0,
			path: "/",
			domain: $domain,
			secure: $scheme === "https",
			httpOnly: false,
			raw: false,
			sameSite: "lax"
		);

		return redirect()
			->away("{$frontendUrl}")
			->withCookie($cookie);
	}

	/**
	 * Fail-closed: qualsiasi valore non riconosciuto → SAML. OIDC solo dove il tenant ce l'ha,
	 * altrimenti le route `oidc.sim.*` non esistono nemmeno e il redirect esploderebbe.
	 */
	private function resolveProtocol(mixed $protocol): string
	{
		if ((string) $protocol === self::PROTOCOL_OIDC && config("app.tenant") === "nexi-multi") {
			return self::PROTOCOL_OIDC;
		}

		return self::PROTOCOL_SAML;
	}

	/** Risposta d'errore XML coerente per l'endpoint SAML. */
	private function samlError(int $code, string $message): string
	{
		return '<?xml version="1.0" encoding="UTF-8"?>' .
			"\n" .
			"<error><code>" .
			$code .
			"</code><message>" .
			htmlspecialchars($message, ENT_XML1) .
			"</message></error>";
	}

	/**
	 * Legge gli attributi SAML SOLO dai nodi coperti dalla firma (anti-XSW): mai con XPath globale.
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
			$xp->registerNamespace("saml", "urn:oasis:names:tc:SAML:2.0:assertion");

			foreach ($xp->query(".//saml:AttributeStatement/saml:Attribute", $node) as $attr) {
				$name = $attr->getAttribute("Name");
				$valNode = $xp->query("./saml:AttributeValue", $attr)->item(0);
				$val = $valNode ? trim($valNode->textContent) : null;
				if ($name !== "" && $val !== null) {
					$attrs[$name] = $val;
				}
			}
		}

		return $attrs;
	}

	private function resolveSelectedBancaAbi(mixed $bancaAbi): ?string
	{
		$bancaAbi = trim((string) $bancaAbi);

		if ($bancaAbi === "") {
			return null;
		}

		return str_pad($bancaAbi, 5, "0", STR_PAD_LEFT);
	}

	/**
	 * The platform situation the user is about to land on, for the log line.
	 *
	 * Read straight off UserResource, which is what the frontend is handed
	 * after login: the log then says what the operator should actually see,
	 * instead of a second opinion that can drift away from it.
	 *
	 * @return array<string, mixed>
	 */
	private function situazionePiattaforma(?User $user): array
	{
		if ($user === null) {
			return ["situazione" => "utente non trovato"];
		}

		$payload = UserResource::make($user)->resolve(request());
		$credito = $payload["platform_credito"] ?? [];
		$tiraggio = $credito["tiraggio"] ?? null;
		// Multi-tiraggio hands back a list of accepted tiraggi, not one object.
		$tiraggioCorrente = is_array($tiraggio) && array_is_list($tiraggio) ? $tiraggio[0] ?? null : $tiraggio;
		$prodotto = $user->prodottoBanca();

		return [
			"banca_abi" => $user->banca_abi,
			"eleggibile" => $payload["eleggibile"] ?? null,
			"eleggibilita_errors" => $payload["eleggibilita_errors"] ?? [],
			"is_in_target" => $payload["is_in_target"] ?? null,
			"target_errors" => $payload["target_errors"] ?? [],
			// What the home page will render. null there means no warning at
			// all, i.e. the user lands on the tiraggio block itself.
			"schermata_attesa" => $payload["platform_home_translate_code"] ?? "blocco_tiraggio",
			"tiraggio_status" => $tiraggioCorrente["status"] ?? null,
			"tiraggi_in_payload" => is_array($tiraggio) && array_is_list($tiraggio) ? count($tiraggio) : ($tiraggio ? 1 : 0),
			"delibera_status" => $credito["delibera"]["status"] ?? null,
			"tiraggi_accepted" => $credito["accepted_tiraggi_count"] ?? null,
			"multi_tiraggio" => (bool) $prodotto?->multi_tiraggio,
			"durata_parametrica" => (bool) $user->banca?->tiraggio_durata_parametrica,
			"conguaglio_periodico" => (bool) $prodotto?->conguaglio_periodico,
			"metodo_conguaglio" => $prodotto?->metodo_conguaglio,
		];
	}
}
