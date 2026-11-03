@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <a href="{{ url('admin/logs') }}" class="text-decoration-none text-muted me-2" title="Torna alla lista">
            <i class="la la-arrow-left"></i>
        </a>
        <i class="la la-file-text"></i> {{ $fileDisplayName }}
    </h1>
</section>
@endsection

@push('after_styles')
<link rel="stylesheet" href="/static/css/logs_show.css">
@endpush

@section('content')
<div class="container-fluid">
    @if($isTruncatedPreview ?? false)
        <div class="alert alert-warning">
            <i class="la la-exclamation-triangle me-1"></i>
            Preview parziale: file troppo grande, viene mostrata solo una porzione del log.
        </div>
    @endif

    {{-- File Info Bar --}}
    <div class="card mb-3">
        <div class="card-body py-2">
            <div class="d-flex flex-wrap align-items-center gap-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="la la-folder text-muted"></i>
                    <small class="text-muted">{{ $fileName }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="la la-hdd text-muted"></i>
                    <small class="text-muted">{{ $fileSize }}</small>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <i class="la la-clock text-muted"></i>
                    <small class="text-muted">{{ $fileModified }}</small>
                </div>
                <div class="ms-auto d-flex gap-2">
                    <a href="{{ url('admin/logs/export') }}?file={{ urlencode($fileName) }}" 
                       class="btn btn-sm btn-outline-success">
                        <i class="la la-download"></i> Scarica
                    </a>
                    <a href="{{ url('admin/logs/view') }}?file={{ urlencode($fileName) }}" 
                       target="_blank"
                       class="btn btn-sm btn-outline-secondary">
                        <i class="la la-code"></i> Raw
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Log Content --}}
    <div class="card">
        <div class="card-body p-0">
            <pre class="log-content mb-0">{!! $formattedContent !!}</pre>
        </div>
    </div>
</div>

@push('after_scripts')
<script>
function copyLogEntry(btn) {
    const entry = btn.closest('.log-entry');
    if (!entry) return;
    const text = entry.textContent.replace(/\s*\u00a0\s*/g, ' ').trim();
    navigator.clipboard.writeText(text).then(function() {
        btn.classList.add('copied');
        btn.innerHTML = '<i class="la la-check"></i>';
        setTimeout(function() {
            btn.classList.remove('copied');
            btn.innerHTML = '<i class="la la-copy"></i>';
        }, 1500);
    });
}
</script>
@endpush
@endsection
