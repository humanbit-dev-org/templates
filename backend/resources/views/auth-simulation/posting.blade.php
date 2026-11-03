<!doctype html>
<html lang="it">
<head>
    <meta charset="utf-8">
    <title>Posting SAMLResponse…</title>
    <link rel="stylesheet" href="{{ asset('static/css/saml_simulation.css') }}">
</head>
<body class="saml-sim-posting" onload="document.forms[0].submit()">
<div class="saml-sim-posting__panel">
    <div class="saml-sim-posting__spinner" aria-hidden="true"></div>
    <p>Invio SAMLResponse a <code>{{ $acs }}</code>…</p>
    <p style="color: var(--saml-muted); font-size: 0.85rem;">Utente: <code>{{ $simulatedUserId }}</code></p>
</div>
<form method="POST" action="{{ $acs }}">
    @csrf
    <input type="hidden" name="SAMLResponse" value="{{ $samlResponse }}">
    @if($relayState)
        <input type="hidden" name="RelayState" value="{{ $relayState }}">
    @endif
    <noscript><button type="submit">Invia</button></noscript>
</form>
</body>
</html>
