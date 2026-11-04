<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Codice di Accesso</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; line-height: 1.6; color: #1a1a2e; background-color: #eef0f8; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 0 auto; padding: 24px 16px; }
        .card { border-radius: 8px; box-shadow: 0 8px 24px rgba(0, 0, 74, 0.1); overflow: hidden; }
        .header { background: #00004a; color: #ffffff; padding: 28px 24px; text-align: center; }
        .header img { display: block; height: 26px; margin: 0 auto; width: auto; }
        .content { padding: 36px 40px; background: #ffffff; border-left: 1px solid #d4d9f0; border-right: 1px solid #d4d9f0; border-top: 4px solid #0047ff; }
        .content h1 { color: #00004a; font-size: 20px; margin: 0 0 16px; }
        .token { font-size: 30px; font-weight: bold; text-align: center;
                background: #f4f6fc; padding: 18px; margin: 20px 0;
                border: 1px solid #0047ff; border-radius: 6px;
                letter-spacing: 5px; color: #00004a; }
        .footer { text-align: center; font-size: 12px; color: #c8d0ef; background: #00004a; padding: 24px 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
        <div class="header">
            {{-- Brand logo: MAIL_LOGO_URL when set, otherwise the template logo. --}}
            <img src="{{ config('mail.logo_url') ?: url('/static/images/logo.png') }}" alt="{{ config('mail.brand_name') }}" style="display: block; height: 26px; max-height: 26px; width: auto; border: 0; margin: 0 auto;">
        </div>

        <div class="content">
            <h1>Autenticazione a Due Fattori</h1>

            <p>Gentile {{ $user->name }},</p>

            <p>Il tuo codice di accesso per l'autenticazione a due fattori è:</p>

            <div class="token">{{ $token }}</div>

            <p><strong>Importante:</strong></p>
            <ul>
                <li>Questo codice è valido per {{ $trustDays }} giorni</li>
                <li>Non condividere questo codice con nessuno</li>
                <li>Se non hai richiesto l'accesso, ignora questa email</li>
            </ul>

            <p>Inserisci questo codice nella schermata di autenticazione per completare l'accesso.</p>

            <p>Cordiali saluti,<br>{{ config('mail.brand_name') }}</p>
        </div>

        <div class="footer">
            <p>Questa è un'email automatica, si prega di non rispondere a questo messaggio.</p>
            <p>© {{ date('Y') }} {{ config('mail.brand_name') }}. Tutti i diritti riservati.</p>
        </div>
        </div>
    </div>
</body>
</html>
