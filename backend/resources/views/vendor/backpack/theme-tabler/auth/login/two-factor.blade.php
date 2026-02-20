@extends(backpack_view('layouts.auth'))

@section('content')
<div class="page page-center">
    <div class="container container-tight py-4">
        <div class="text-center py-4 display-6 auth-logo-container" style="background-color:{!! backpack_theme_config('project_color') !!};">
            {!! backpack_theme_config('project_logo') !!}
        </div>
        <div class="card card-md">
            <div class="card-body pt-0">
                <h2 class="h2 text-center my-4">Autenticazione a Due Fattori</h2>

                <div class="text-center mb-4">
                    <p class="text-muted">
                        Abbiamo inviato un codice di 6 cifre al tuo indirizzo email.<br>
                        Inserisci il codice qui sotto per completare l'accesso.
                    </p>
                </div>

                @if (session('status'))
                <div class="alert alert-success" role="alert">
                    {{ session('status') }}
                </div>
                @endif

                @error('throttle')
                <div class="alert alert-danger" role="alert">
                    {{ $message }}
                </div>
                @enderror

                <form method="POST" action="{{ route('backpack.auth.two-factor.verify') }}">
                    @csrf

                    <div class="mb-3">
                        <label class="form-label" for="token">Codice di Verifica</label>
                        <input type="text"
                            class="form-control text-center @error('token') is-invalid @enderror"
                            id="token"
                            name="token"
                            placeholder="000000"
                            maxlength="6"
                            pattern="[0-9]{6}"
                            autocomplete="off"
                            style="font-size: 24px; letter-spacing: 8px; font-weight: bold;"
                            autofocus
                            required>

                        @error('token')
                        <div class="invalid-feedback">
                            {{ $message }}
                        </div>
                        @enderror
                    </div>

                    <div class="form-footer">
                        <button type="submit" id="submit-btn" class="btn btn-primary w-100">
                            <span class="submit-text">Verifica Codice</span>
                            <span class="submit-loading d-none">
                                <span class="spinner-border spinner-border-sm me-2" role="status"></span>
                                Verificando...
                            </span>
                        </button>
                    </div>
                </form>

                <div class="text-center text-muted mt-3">
                    <p class="mb-2">Non hai ricevuto il codice?</p>
                    <form method="POST" action="{{ route('backpack.auth.two-factor.resend') }}" class="d-inline">
                        @csrf
                        <button type="submit"
                            class="btn btn-link p-0 text-decoration-underline @error('throttle') disabled @enderror"
                            @error('throttle') disabled @enderror>
                            Invia nuovo codice
                        </button>
                    </form>
                </div>

                <div class="text-center text-muted mt-4">
                    <a href="{{ route('backpack.auth.login') }}" class="btn btn-ghost-secondary">
                        ← Torna al Login
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const tokenInput = document.getElementById('token');
    const submitBtn = document.getElementById('submit-btn');
    const submitText = submitBtn.querySelector('.submit-text');
    const submitLoading = submitBtn.querySelector('.submit-loading');
    const form = tokenInput.closest('form');

    let isSubmitting = false;

    // Function to disable submit button and show loading state
    function disableSubmitButton() {
        if (isSubmitting) return;

        isSubmitting = true;
        submitBtn.disabled = true;
        submitText.classList.add('d-none');
        submitLoading.classList.remove('d-none');
    }

    // Function to enable submit button and hide loading state
    function enableSubmitButton() {
        isSubmitting = false;
        submitBtn.disabled = false;
        submitText.classList.remove('d-none');
        submitLoading.classList.add('d-none');
    }

    // Auto-format token input
    tokenInput.addEventListener('input', function(e) {
        // Remove any non-numeric characters
        e.target.value = e.target.value.replace(/\D/g, '');

        // Limit to 6 digits
        if (e.target.value.length > 6) {
            e.target.value = e.target.value.substring(0, 6);
        }
    });

    // Auto-submit form when 6 digits are entered
    tokenInput.addEventListener('input', function(e) {
        if (e.target.value.length === 6 && !isSubmitting) {
            // Small delay to let user see the completed input
            setTimeout(() => {
                if (!isSubmitting) {
                    disableSubmitButton();
                    form.submit();
                }
            }, 500);
        }
    });

    // Handle manual form submission
    form.addEventListener('submit', function(e) {
        if (!isSubmitting) {
            disableSubmitButton();
        }
    });

    // Re-enable button if form submission fails (page doesn't redirect)
    window.addEventListener('pageshow', function() {
        enableSubmitButton();
    });

    // Handle validation errors (when page reloads due to errors)
    document.addEventListener('DOMContentLoaded', function() {
        // If there are validation errors, make sure button is enabled
        if (document.querySelector('.is-invalid') || document.querySelector('.alert-danger')) {
            enableSubmitButton();
        }
    });
</script>
@endsection