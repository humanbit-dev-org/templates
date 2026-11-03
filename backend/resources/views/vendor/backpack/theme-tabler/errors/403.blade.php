@extends(backpack_view('errors.blank'))

@php
  $error_number = 403;
@endphp

@section('content')
    <div class="text-center mt-5">
        <h1>403</h1>
        <p>{{ $exception?->getMessage() ?: 'Accesso negato.' }}</p>
    </div>
@endsection
