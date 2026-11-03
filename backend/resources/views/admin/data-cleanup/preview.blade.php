@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="{{ $command['icon'] }}"></i> {{ $command['title'] }}
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <a href="{{ route('data-cleanup.index') }}" class="btn btn-sm btn-outline-secondary mb-3">
            <i class="la la-arrow-left"></i> Torna alla lista
        </a>

        <div class="card mb-3">
            <div class="card-body">
                <p class="text-muted mb-2">{{ $command['description'] }}</p>
                <div>
                    @foreach ($command['affected_tables'] as $table)
                        <span class="badge bg-light text-dark border me-1">Tabella: {{ $table }}</span>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Summary --}}
        <div class="card mb-3">
            <div class="card-header">
                <h5 class="card-title mb-0"><i class="la la-chart-bar"></i> Riepilogo</h5>
            </div>
            <div class="card-body">
                @if (empty($result['summary']))
                    <p class="text-muted mb-0">Nessun dato da elaborare.</p>
                @else
                    <div class="row g-3">
                        @foreach ($result['summary'] as $label => $value)
                            <div class="col-6 col-md-3">
                                <div class="border rounded p-2 text-center">
                                    <div class="fw-bold fs-5">{{ is_numeric($value) ? number_format($value, 0, ',', '.') : $value }}</div>
                                    <div class="text-muted small text-capitalize">{{ str_replace('_', ' ', $label) }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                @if (! empty($result['warnings']))
                    <div class="mt-3">
                        @foreach ($result['warnings'] as $warning)
                            <div class="alert alert-warning py-2 mb-1">
                                <i class="la la-exclamation-triangle"></i> {{ $warning }}
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Details log --}}
        @if (! empty($result['details']))
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0"><i class="la la-list"></i> Dettagli operazioni</h5>
                    <button class="btn btn-sm btn-outline-secondary" onclick="copyDetails()">
                        <i class="la la-copy"></i> Copia
                    </button>
                </div>
                <div class="card-body p-0">
                    <pre id="details-log" class="mb-0 p-3" style="max-height: 500px; overflow-y: auto; font-size: 0.8rem; background: #f8f9fa; white-space: pre-wrap; word-break: break-word;">{{ implode("\n", $result['details']) }}</pre>
                </div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="d-flex gap-2 justify-content-between">
            <a href="{{ route('data-cleanup.index') }}" class="btn btn-outline-secondary">
                <i class="la la-times"></i> Annulla
            </a>
            @if ($result['can_execute'])
                <form action="{{ route('data-cleanup.execute', $commandId) }}" method="POST">
                    @csrf
                    @foreach ($parameters as $key => $value)
                        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
                    @endforeach
                    <button type="submit" class="btn btn-danger"
                            onclick="return confirm('Confermi di eseguire l\'operazione? Verrà creato un backup automatico prima dell\'esecuzione.')">
                        <i class="la la-play"></i> Esegui
                    </button>
                </form>
            @else
                <button type="button" class="btn btn-secondary" disabled>
                    <i class="la la-ban"></i> Esegui
                </button>
            @endif
        </div>
    </div>
</div>

@push('after_scripts')
<script>
    function copyDetails() {
        var pre = document.getElementById('details-log');
        if (pre) {
            navigator.clipboard.writeText(pre.textContent).then(function() {
                alert('Dettagli copiati negli appunti.');
            });
        }
    }
</script>
@endpush
@endsection
