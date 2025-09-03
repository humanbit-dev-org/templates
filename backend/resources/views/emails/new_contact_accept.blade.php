<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('contacts.new_contact_accept_title')}} {{ $ecommerce->name }}</title>
</head>

<body>
    <h1 style="font-weight:bold;">{{ __('contacts.new_contact_accept_title')}} {{ $ecommerce->name }}</h1>
    <p>{{ __('contacts.new_contact_accept_subtitle_1')}}</p>
    <p>{{ __('contacts.new_contact_accept_subtitle_2')}}</p>
    <ul>
        <li>{{ __('contacts.new_contact_accept_ecommerce_public_key')}} {{ $ecommerce->public_key }} </li>
        <li>{{ __('contacts.new_contact_accept_ecommerce_url')}} {{ $ecommerce->url }} </li>
        <li>{{ __('contacts.new_contact_accept_ecommerce_order_time')}} {{ $ecommerce->order_time }} {{ $ecommerce->unit_type }}</li>
    </ul>
    @if($isNewUser)
    <p>{{ __('contacts.new_contact_accept_subtitle_3')}}</p>
    <ul>
        <li>{{ __('contacts.new_contact_accept_user_username')}} {{ $user->username }} </li>
        <li>{{ __('contacts.new_contact_accept_user_password')}} {{ $plainPassword }} </li>
    </ul>
    <p>{{ __('contacts.new_contact_accept_subtitle_4')}}</p>
    @endif
    <p>{{ __('contacts.alltogetherpay_footer')}}</p>
</body>

</html>