@props(['coBrandLogoUrl' => null, 'coBrandName' => null])
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
{{-- The logo links to the frontend users know, not to the backend (app.url). --}}
<x-mail::header :url="config('app.frontend_url')" :co-brand-logo-url="$coBrandLogoUrl" :co-brand-name="$coBrandName">
{{ config('mail.brand_name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
© {{ date('Y') }} {{ config('mail.brand_name') }}. {{ __('All rights reserved.') }}<br>
{{ __('This is an automated message, please do not reply.') }}
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
