@php
    $userRole = strtolower(backpack_auth()->user()->backpackRole?->name ?? '');
    $isDeveloper = $userRole === 'developer';
    // Footer renders for admin too, but admin only gets the AUTH Sim button.
    $canSeeLinks = $isDeveloper || $userRole === 'admin';
    $restrictedTools = app(\App\Services\Restricted\RestrictedToolAccessService::class);
@endphp

@if ($canSeeLinks)
    <div class="useful-links-footer" role="contentinfo" aria-label="Useful links">
        <div class="useful-links-footer__inner">
            @if ($restrictedTools->isEnabledInCurrentEnvironment('auth_sim'))
            <a href="{{ url('/auth-simulation') }}" target="_blank" rel="noopener noreferrer" class="useful-link-btn" title="Auth Simulation (SAML / OIDC)">
                <i class="la la-key"></i>
                <span class="useful-link-label">AUTH Simulation</span>
            </a>
            @endif
            @if ($isDeveloper && $restrictedTools->isEnabledInCurrentEnvironment('swagger'))
            <a href="{{ route('scramble.docs.ui') }}" target="_blank" rel="noopener noreferrer" class="useful-link-btn" title="API Documentation">
                <i class="la la-book"></i>
                <span class="useful-link-label">Swagger</span>
            </a>
            @endif
            @if ($isDeveloper && $restrictedTools->isEnabledInCurrentEnvironment('api_docs'))
            <a href="{{ url('/api-docs') }}" target="_blank" rel="noopener noreferrer" class="useful-link-btn" title="API Docs">
                <i class="la la-plug"></i>
                <span class="useful-link-label">API Docs</span>
            </a>
            @endif
            @if ($isDeveloper && $restrictedTools->isEnabledInCurrentEnvironment('api_lab'))
            <a href="{{ url('/api-lab') }}" target="_blank" rel="noopener noreferrer" class="useful-link-btn" title="API Lab">
                <i class="la la-flask"></i>
                <span class="useful-link-label">API Lab</span>
            </a>
            @endif
            @if ($isDeveloper && $restrictedTools->isEnabledInCurrentEnvironment('horizon'))
            <a href="{{ config('app.url') }}/horizon" target="_blank" rel="noopener noreferrer" class="useful-link-btn" title="Laravel Horizon">
                <i class="la la-server"></i>
                <span class="useful-link-label">Horizon</span>
            </a>
            @endif
            @if ($isDeveloper && $restrictedTools->isEnabledInCurrentEnvironment('pulse'))
            <a href="{{ config('app.url') }}/pulse" target="_blank" rel="noopener noreferrer" class="useful-link-btn" title="Laravel Pulse">
                <i class="la la-heartbeat"></i>
                <span class="useful-link-label">Pulse</span>
            </a>
            @endif
        </div>
    </div>
@endif
