@props(['url', 'coBrandLogoUrl' => null, 'coBrandName' => null])
@php
    // Brand logo: MAIL_LOGO_URL when set, otherwise the template logo.
    // An optional co-brand logo (e.g. a partner) is shown next to it.
    $brandLogoUrl = config('mail.logo_url') ?: url('/static/images/logo.png');
@endphp
<tr>
<td class="header" bgcolor="#00004a" style="background-color: #00004a; padding: 28px 24px; text-align: center;">
<table align="center" cellpadding="0" cellspacing="0" role="presentation" style="margin: 0 auto;">
<tr>
<td align="center" valign="middle" style="padding-right: {{ $coBrandLogoUrl ? '20px' : '0' }};">
<a href="{{ $url }}" style="display: inline-block; text-decoration: none;">
@if (trim($slot) === 'Laravel')
<img src="https://laravel.com/img/notification-logo-v2.1.png" class="logo logo-brand" alt="Laravel Logo" style="display: block; height: 26px; max-height: 26px; width: auto; border: 0;">
@else
<img src="{{ $brandLogoUrl }}" class="logo logo-brand" alt="{{ config('mail.brand_name') }}" width="84" height="26" style="display: block; height: 26px; max-height: 26px; width: auto; border: 0;">
@endif
</a>
</td>
@if ($coBrandLogoUrl)
<td align="center" valign="middle" style="border-left: 1px solid rgba(255,255,255,0.35); padding-left: 20px;">
<img src="{{ $coBrandLogoUrl }}" class="logo logo-cobrand" alt="{{ $coBrandName ?? '' }}" style="display: block; max-height: 40px; height: auto; max-width: 180px; width: auto; border: 0;">
</td>
@endif
</tr>
</table>
</td>
</tr>
