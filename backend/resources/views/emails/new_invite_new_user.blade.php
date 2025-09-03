<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('invites.new_invite_new_user_title')}} {{ $group->name }}</title>
</head>

<body>
    <h1 style="font-weight:bold;">{{ __('invites.new_invite_new_user_title')}} {{ $group->name }}</h1>
    <p>{{ __('invites.new_invite_new_user_subtitle_1')}}.</p>
    <p style="font-size:20px;">{{ __('invites.invitation_received_1')}} <span style="font-weight:bold;">{{ '@' . $username }}</span>, {{ __('invites.invitation_received_2')}}</p>

    <p style="font-size:20px;">{{ __('invites.new_invite_new_user_sign_up')}}, <a href="{{ 'http://localhost:3000/?invite=yes&email=' . urlencode($toEmail) }}">{{ __('invites.click_here')}}.</a></p>
    <h3 style="font-weight:bold;">{{ __('invites.all_together_pay_team')}}</h3>
</body>

</html>