@extends(backpack_view('blank'))

@push('after_styles')
<link rel="stylesheet" href="/static/css/csv_rollback.css">
@endpush

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <a href="{{ route('csv-rollback.index') }}" class="text-decoration-none text-muted me-2" title="Torna alla lista">
            <i class="la la-arrow-left"></i>
        </a>
        <i class="la la-file-code"></i> {{ $fileInfo['table'] ?? 'Backup' }}
    </h1>
</section>
@endsection

@section('content')
{{-- Loading Overlay --}}
<div id="page-loading-overlay" class="page-loading-overlay">
    <div class="page-loading-spinner"></div>
    <div class="page-loading-text">Caricamento SQL in corso...</div>
</div>

<div class="container-fluid">
    {{-- File Info Bar --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="la la-table text-muted"></i>
                    <small class="text-muted">Tabella: <strong>{{ $fileInfo['table'] ?? 'N/A' }}</strong></small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="la la-clock text-muted"></i>
                    <small class="text-muted">{{ $fileInfo['generated'] ?? 'N/A' }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="la la-list-ol text-muted"></i>
                    <small class="text-muted">
                        @if(!is_null($fileInfo['row_count']))
                            ~{{ number_format($fileInfo['row_count']) }} righe
                        @else
                            righe: n/d
                        @endif
                    </small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    @if ($fileInfo['has_structure'])
                        <span class="badge bg-success text-white">Struttura inclusa</span>
                    @endif
                    @if ($fileInfo['has_data'])
                        <span class="badge bg-info text-white">Dati inclusi</span>
                    @endif
                    @if ($isTruncatedPreview)
                        <span class="badge bg-warning text-dark">Preview parziale</span>
                    @endif
                </div>
                <div class="ms-auto d-flex align-items-center csv-rollback-preview-actions">
                    <div class="csv-rollback-danger-action me-3 pe-3">
                        <button type="button" class="btn btn-outline-danger" onclick="confirmDelete()">
                            Elimina Backup
                        </button>
                    </div>
                    <div class="d-flex gap-2">
                        <a href="{{ route('csv-rollback.download', ['file' => $file]) }}" class="btn btn-success text-white">
                            <i class="la la-download"></i> Scarica Backup
                        </a>
                        <button type="button" class="btn btn-info text-white" onclick="confirmRestore()">
                            Ripristina Backup
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    @if ($isTruncatedPreview)
        <div class="alert alert-warning">
            <i class="la la-exclamation-triangle me-1"></i>
            Il file e molto grande: per evitare errori di memoria viene mostrata solo una porzione iniziale.
        </div>
    @endif

    {{-- SQL Content --}}
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center py-2">
            <span><i class="la la-code"></i> Contenuto SQL</span>
            <button class="btn btn-sm btn-outline-secondary" onclick="copyContent()">
                <i class="la la-copy"></i> Copia
            </button>
        </div>
        <div class="card-body p-0">
            <pre class="sql-content mb-0" id="sql-content">{!! $highlightedContent !!}</pre>
        </div>
    </div>
</div>

{{-- Hidden Form --}}
<form id="restore-form" action="{{ route('csv-rollback.restore') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="file" value="{{ $file }}">
</form>

<form id="delete-form" action="{{ route('csv-rollback.delete') }}" method="POST" style="display: none;">
    @csrf
    <input type="hidden" name="file" value="{{ $file }}">
</form>
@endsection

@push('after_scripts')
<script>
    function confirmRestore() {
        const table = '{{ addslashes($fileInfo['table'] ?? 'la tabella') }}';
        if (confirm(`⚠️ ATTENZIONE!\n\nStai per ripristinare la tabella "${table}" allo stato del backup selezionato.\n\nQuesta operazione:\n• Eliminerà TUTTI i dati attuali della tabella\n• Ripristinerà i dati dal backup\n• NON può essere annullata\n\nSei sicuro di voler procedere?`)) {
            document.getElementById('restore-form').submit();
        }
    }

    function copyContent() {
        const content = document.getElementById('sql-content').textContent;
        navigator.clipboard.writeText(content).then(() => {
            alert('Contenuto SQL copiato negli appunti!');
        }).catch(err => {
            console.error('Errore durante la copia:', err);
        });
    }

    function confirmDelete() {
        if (confirm('Eliminare questo backup CSV? Questa azione non e reversibile.')) {
            document.getElementById('delete-form').submit();
        }
    }

    // Hide loading overlay when page is fully loaded
    document.addEventListener('DOMContentLoaded', function() {
        const overlay = document.getElementById('page-loading-overlay');
        if (overlay) {
            overlay.classList.add('fade-out');
            setTimeout(() => overlay.remove(), 300);
        }
    });
</script>
@endpush


