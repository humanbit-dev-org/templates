<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Codice di Accesso</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #007bff; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f8f9fa; }
        .token { font-size: 32px; font-weight: bold; text-align: center; 
                background: #fff; padding: 20px; margin: 20px 0; 
                border: 2px solid #007bff; border-radius: 5px; 
                letter-spacing: 5px; }
        .footer { text-align: center; font-size: 12px; color: #666; margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Autenticazione a Due Fattori</h1>
        </div>
        
        <div class="content">
            <p>Ciao {{ $user->name }},</p>
            
            <p>Il tuo codice di accesso per l'autenticazione a due fattori è:</p>
            
            <div class="token">{{ $token }}</div>
            
            <p><strong>Importante:</strong></p>
            <ul>
                <li>Questo codice è valido per 30 giorni</li>
                <li>Non condividere questo codice con nessuno</li>
                <li>Se non hai richiesto l'accesso, ignora questa email</li>
            </ul>
            
            <p>Inserisci questo codice nella schermata di autenticazione per completare l'accesso.</p>
        </div>
        
        <div class="footer">
            <p>Questa è un'email automatica, non rispondere a questo messaggio.</p>
        </div>
    </div>
</body>
</html> 