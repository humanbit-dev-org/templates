<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>API Docs — Documentazione API</title>
    <link rel="stylesheet" href="{{ asset('static/css/api_sandbox.css') }}">
</head>
<body class="api-sandbox-page">

@if ($tabs->count() > 1)
<div class="api-sandbox-tabs api-docs-tabs" role="tablist" aria-label="Ambiente">
    <a href="{{ url('/api-docs?tab=sandbox') }}" role="tab" data-tab="sandbox"
        class="api-sandbox-tab {{ $tab === 'sandbox' ? 'active' : '' }}"
        aria-selected="{{ $tab === 'sandbox' ? 'true' : 'false' }}">
        Sandbox
        <small>dati finti, rollback</small>
    </a>
    <a href="{{ url('/api-docs?tab=portal') }}" role="tab" data-tab="portal"
        class="api-sandbox-tab {{ $tab === 'portal' ? 'active' : '' }}"
        aria-selected="{{ $tab === 'portal' ? 'true' : 'false' }}">
        Portal
        <small>produzione, dati reali</small>
    </a>
</div>
@endif

<script>
    // Bare /api-docs renders the cookie-remembered tab already; here we only
    // put ?tab=… into the address bar — no reload, no history entry.
    if (!new URLSearchParams(location.search).has('tab')) {
        history.replaceState(null, '', '{{ url('/api-docs?tab='.$tab) }}');
    }
</script>

@if ($tab === 'sandbox')
    @include('api-docs.sandbox')
@else
    @include('api-docs.portal')
@endif

</body>
</html>
