<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('contacts.new_contact_reject_title')}}</title>
</head>

<body>
    <h1 style="font-weight:bold;">{{ __('contacts.new_contact_reject_title')}}</h1>
    <p>{{ __('contacts.new_contact_reject_subtitle_1')}}</p>

    @if($rejectReason)
    <p>{{ __('contacts.new_contact_reject_reason_intro')}}</p>
    <p style="padding: 15px; background-color: #f8f9fa; border-left: 4px solid #dc3545;">
        {{ $rejectReason }}
    </p>
    @endif

    <p>{{ __('contacts.alltogetherpay_footer')}}</p>
</body>

</html>