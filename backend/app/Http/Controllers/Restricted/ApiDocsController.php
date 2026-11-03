<?php

namespace App\Http\Controllers\Restricted;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Restricted\RestrictedToolAccessService;
use App\Support\ApiSandbox\EndpointsCatalog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * /api-docs — unified API documentation page.
 *
 * One page with a Sandbox | Portal tab toggle replacing the standalone
 * /api-sandbox and /api-portal pages (both redirect here). Each tab is
 * shown only where its own surface exists: the sandbox tab needs
 * sandbox.enabled + the api_sandbox tool, the portal tab needs
 * portal.enabled + the api_portal tool — so production renders the
 * Portal pane alone.
 *
 * The Portal pane lists the issued refresh tokens and the
 * currently live access tokens (api tokens) — both semi-masked: only
 * metadata is ever rendered, since PATs are stored as hashes.
 */
class ApiDocsController extends Controller
{
    public function index(Request $request, RestrictedToolAccessService $tools): \Illuminate\Http\Response
    {
        // Each tab follows its own surface: a tab pointing at a disabled
        // feature would document dead routes.
        $tabs = collect([
            'sandbox' => (bool) config('sandbox.enabled')
                && $tools->isEnabledInCurrentEnvironment('api_sandbox'),
            'portal' => (bool) config('portal.enabled')
                && $tools->isEnabledInCurrentEnvironment('api_portal'),
        ])->filter()->keys();

        abort_if($tabs->isEmpty(), 404);

        // Explicit ?tab= wins; otherwise the remembered choice travels in a
        // cookie so the server renders the right pane on first paint — no
        // client-side hop, no flash of the wrong tab.
        $tab = $request->query('tab');
        if (! $tabs->contains($tab)) {
            $tab = $tabs->contains($request->cookie('api-docs-tab'))
                ? $request->cookie('api-docs-tab')
                : $tabs->first();
        }

        $user = backpack_auth()->user();
        $isStaff = $user?->backpackRole?->name === 'Developer';

        // Deliberately no bank-modality filtering of the endpoint catalog:
        // a conguaglio_only gating existed and was removed — the only
        // modality-gated endpoint today is estinzione-parziale, and the
        // endpoint itself already enforces the flag at request time. If a
        // bank-viewer backpack role is ever added and needs scoped docs,
        // rebuild the filter then — do not pre-build it.
        $data = $tab === 'sandbox' ? $this->sandboxData() : $this->portalData($user, $isStaff);

        // The remembered tab is stored as a server-set cookie — JS cookies
        // are plaintext and EncryptCookies would fail to decrypt them.
        return response()
            ->view('api-docs.index', array_merge(['tab' => $tab, 'tabs' => $tabs], $data))
            ->withCookie(cookie('api-docs-tab', $tab, 60 * 24 * 365, '/api-docs'));
    }

    /** Same data the old ApiSandboxController served. */
    private function sandboxData(): array
    {
        return [
            'endpoints' => EndpointsCatalog::all(),
            'sandboxEnabled' => (bool) config('sandbox.enabled'),
            'banks' => config('sandbox.banks', []),
            'defaultBank' => (string) config('sandbox.default_bank'),
        ];
    }

    /** Same data the old ApiPortalController served, plus the live access tokens. */
    private function portalData(?User $user, bool $isStaff): array
    {
        // Credential visibility by viewer: developers see everything
        // (the per-bank log card and the refresh-token table), any other
        // logged-in user sees only their own bank's latest token in the top
        // bar, and an IP-only guest sees none of it.
        $viewerAbi = $user?->banca_abi;

        $refreshTokens = collect();
        $apiTokens = collect();

        if ($isStaff) {
            $prefix = config('portal.refresh_token_prefix').':';

            $refreshTokens = PersonalAccessToken::query()
                ->where('name', 'like', $prefix.'%')
                ->whereMorphedTo('tokenable', User::class)
                ->with('tokenable.banca')
                ->orderBy('name')
                ->get()
                ->map(fn (PersonalAccessToken $token) => [
                    'banca' => $token->tokenable->banca?->nome ?? $token->tokenable->banca_abi,
                    'user_id' => $token->tokenable->user_id,
                    // The name carries the secret's first chars: the semi-masked
                    // hint the page is allowed to show. Never the full secret.
                    'hint' => Str::after($token->name, ':').'••••••••',
                    'created_at' => $token->created_at,
                    'last_used_at' => $token->last_used_at,
                ]);
        }

        if ($user) {
            // Each trade revokes the previous token, so the newest access
            // token per PO is also the only live one.
            $apiTokens = PersonalAccessToken::query()
                ->where('name', config('portal.api_token_name', 'portal-access'))
                ->whereMorphedTo('tokenable', User::class)
                ->with('tokenable.banca')
                ->orderByDesc('created_at')
                ->get()
                ->unique('tokenable_id')
                ->map(fn (PersonalAccessToken $token) => [
                    'banca' => $token->tokenable->banca?->nome ?? $token->tokenable->banca_abi,
                    'banca_abi' => $token->tokenable->banca_abi,
                    'user_id' => $token->tokenable->user_id,
                    // Last chars of the stored sha256 — a fingerprint to tell
                    // tokens apart. The secret itself is never recoverable.
                    'hint' => '•••'.substr($token->token, -4),
                    'created_at' => $token->created_at,
                    'expires_at' => $token->expires_at,
                    'last_used_at' => $token->last_used_at,
                ])
                ->values();
        }

        // Top bar: staff gets the freshest token overall; a bank user gets
        // their own bank's; guests get nothing.
        $lastToken = match (true) {
            ! $user => null,
            $isStaff => $apiTokens->first(),
            default => $apiTokens->firstWhere('banca_abi', $viewerAbi),
        };

        return [
            'endpoints' => EndpointsCatalog::all('/api/v1'),
            'isStaff' => $isStaff,
            'viewerLogged' => (bool) $user,
            'refreshTokens' => $refreshTokens,
            'tokenLog' => $isStaff ? $apiTokens : collect(),
            'lastToken' => $lastToken,
            'apiTokenHours' => (int) round(config('portal.api_token_minutes') / 60),
        ];
    }
}
