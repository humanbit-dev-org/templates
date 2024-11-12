<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AllTogetherPay - Invito ad entrare al gruppo</title>
</head>
<body>
    <h1 style="font-weight:bold;">AllTogetherPay - Invito ad entrare al gruppo</h1>
    <p style="font-size:20px;">Ciao! L'utente <span style="color:blue;">{{ $email }}</span> ti ha invitato ad accedere al suo gruppo <span style="font-weight:bold;">{{ $group->name }}</span>!</p>
    <p style="font-size:20px;">Per accedere e gestire i tuoi inviti, clicca sul link <a href="http://localhost:3000/groups/invites/">qui</a></p>
    <h3 style="font-weight:bold;">Il team AllTogetherPay</h3>
</body>
</html>