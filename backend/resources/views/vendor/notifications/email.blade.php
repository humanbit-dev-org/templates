@php
    /**
     * Renders MailMessage lines as markdown blocks with balanced spacing.
     * - Consecutive narrative lines share one paragraph (hard breaks).
     * - Consecutive **Label:** detail lines share one compact paragraph.
     * - Switching narrative/detail or an empty ->line('') starts a new block.
     */
    $mailMarkdownLines = static function (array $lines): string {
        $isDetailLine = static fn (?string $line): bool => is_string($line)
            && preg_match('/^\*\*.+\*\*:/', $line) === 1;

        $blocks = [];
        $narrative = [];
        $details = [];

        $flush = static function () use (&$blocks, &$narrative, &$details): void {
            if ($narrative !== []) {
                $blocks[] = implode("  \n", $narrative);
                $narrative = [];
            }

            if ($details !== []) {
                $blocks[] = implode("  \n", $details);
                $details = [];
            }
        };

        foreach ($lines as $line) {
            if ($line === null || $line === '') {
                $flush();

                continue;
            }

            if ($isDetailLine($line)) {
                if ($narrative !== []) {
                    $blocks[] = implode("  \n", $narrative);
                    $narrative = [];
                }

                $details[] = $line;

                continue;
            }

            if ($details !== []) {
                $blocks[] = implode("  \n", $details);
                $details = [];
            }

            $narrative[] = $line;
        }

        $flush();

        return implode("\n\n", $blocks);
    };
@endphp
<x-mail::message :co-brand-logo-url="$coBrandLogoUrl ?? null" :co-brand-name="$coBrandName ?? null">
{{-- Greeting --}}
@if (! empty($greeting))
**{{ $greeting }}**

@elseif ($level === 'error')
**{{ __('Whoops!') }}**

@else
**{{ __('Hello!') }}**

@endif

{{-- Intro Lines --}}
@if (! empty($introLines))
{{ $mailMarkdownLines($introLines) }}

@endif
{{-- Action Button --}}
@isset($actionText)
<?php
    $color = match ($level) {
        'success', 'error' => $level,
        default => 'primary',
    };
?>
<x-mail::button :url="$actionUrl" :color="$color">
{{ $actionText }}
</x-mail::button>
@endisset

{{-- Outro Lines --}}
@if (! empty($outroLines))
{{ $mailMarkdownLines($outroLines) }}

@endif
{{-- Salutation --}}
@if (! empty($salutation))

{{ $mailMarkdownLines(preg_split('/\r\n|\r|\n/', trim($salutation)) ?: []) }}
@else
{{ __('Regards,') }}
{{ config('mail.brand_name') }}
@endif

{{-- Subcopy: fallback link for clients that do not render the button. Translated
     through the JSON lang files (lang/{it,de}.json; English is the key itself). --}}
@isset($actionText)
<x-slot:subcopy>
{{ __('If you\'re having trouble clicking the ":actionText" button, copy and paste the URL below into your web browser:', ['actionText' => $actionText]) }}
<span class="break-all">[{{ $displayableActionUrl }}]({{ $actionUrl }})</span>
</x-slot:subcopy>
@endisset
</x-mail::message>
