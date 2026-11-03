{{--
    Support request email sent by the admin panel support widget
    (AdminSupportRequestNotification).

    Kept deliberately short: category and sender on top, then the message,
    the attached screenshots and a few context details. The admin user is
    identified by email only, and Reply-To points at that address, so
    answering the email reaches the user directly.

    Table layout and inline styles only: many mail clients ignore <style>.
--}}
@php
    // Badge colours per category: bug reports stand out in red, the rest stay neutral.
    $badge = match ($categoryKey) {
        'bug' => ['bg' => '#fee2e2', 'fg' => '#b91c1c'],
        default => ['bg' => '#e0e7ff', 'fg' => '#3730a3'],
    };

    $label = 'margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:0.06em;text-transform:uppercase;color:#6b7280;';
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ __('backpack::support.mail_title') }} — {{ $categoryLabel }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif;color:#374151;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:24px 12px;">
    <tr>
        <td align="center">
            <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;background-color:#ffffff;border:1px solid #e5e7eb;border-radius:10px;overflow:hidden;">

                {{-- Header --}}
                <tr>
                    <td style="background-color:#3d4555;padding:16px 28px;color:#ffffff;font-size:17px;font-weight:700;">
                        {{ __('backpack::support.mail_title') }}
                    </td>
                </tr>

                {{-- What and who: category badge, sender email, time --}}
                <tr>
                    <td style="padding:24px 28px 0;">
                        <span style="display:inline-block;background-color:{{ $badge['bg'] }};color:{{ $badge['fg'] }};font-size:13px;font-weight:700;padding:5px 12px;border-radius:999px;">
                            {{ $categoryLabel }}
                        </span>
                        <p style="margin:12px 0 0;font-size:14px;color:#374151;">
                            {{ __('backpack::support.mail_from') }}
                            <a href="mailto:{{ $user->email }}" style="color:#2563eb;text-decoration:none;font-weight:600;">{{ $user->email }}</a>
                            <span style="color:#9ca3af;">· {{ $sentAt->format('d/m/Y H:i') }}</span>
                        </p>
                    </td>
                </tr>

                {{-- Message: the main content of the email --}}
                <tr>
                    <td style="padding:24px 28px 0;">
                        <p style="{{ $label }}">{{ __('backpack::support.message') }}</p>
                        <div style="background-color:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:16px 18px;font-size:15px;line-height:1.65;color:#111827;white-space:pre-wrap;word-break:break-word;">{{ $messageBody }}</div>
                    </td>
                </tr>

                {{-- Attachments: the files themselves travel as email attachments --}}
                @if (count($screenshotNames) > 0)
                <tr>
                    <td style="padding:24px 28px 0;">
                        <p style="{{ $label }}">{{ __('backpack::support.mail_attachments', ['count' => count($screenshotNames)]) }}</p>
                        @foreach ($screenshotNames as $name)
                            <p style="margin:0 0 4px;font-size:14px;color:#1f2937;">&#128206;&nbsp;{{ $name }}</p>
                        @endforeach
                    </td>
                </tr>
                @endif

                {{-- Context details --}}
                <tr>
                    <td style="padding:24px 28px 24px;">
                        <p style="{{ $label }}">{{ __('backpack::support.mail_details') }}</p>
                        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;font-size:14px;">
                            <tr>
                                <td style="padding:6px 0;width:30%;color:#6b7280;">{{ __('backpack::support.mail_role') }}</td>
                                <td style="padding:6px 0;color:#111827;">{{ $roleName }}</td>
                            </tr>
                            @if (filled($bancaLabel))
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;">{{ __('backpack::support.mail_bank') }}</td>
                                <td style="padding:6px 0;color:#111827;">{{ $bancaLabel }}</td>
                            </tr>
                            @endif
                            @if (filled($pageUrl))
                            <tr>
                                <td style="padding:6px 0;color:#6b7280;vertical-align:top;">{{ __('backpack::support.mail_page') }}</td>
                                <td style="padding:6px 0;word-break:break-all;"><a href="{{ $pageUrl }}" style="color:#2563eb;">{{ $pageUrl }}</a></td>
                            </tr>
                            @endif
                        </table>
                    </td>
                </tr>

                {{-- Footer: how to answer (Reply-To is the sender) --}}
                <tr>
                    <td style="background-color:#f4f6f9;padding:14px 28px;text-align:center;font-size:12px;line-height:1.5;color:#6b7280;">
                        {{ __('backpack::support.mail_reply_hint') }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
</body>
</html>
