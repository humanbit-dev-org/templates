<x-mail::layout>
<x-slot:header>
@include('vendor.mail.html.admin-header', ['url' => config('app.url')])
</x-slot:header>

{!! $slot !!}

<x-slot:footer>
@component('vendor.mail.html.admin-footer')
© {{ date('Y') }} {{ config('app.name') }} Admin. {{ __('backpack::support.mail_footer_notice') }}
@endcomponent
</x-slot:footer>
</x-mail::layout>
