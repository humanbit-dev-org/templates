@extends(backpack_view('blank'))

@push('after_styles')
<link rel="stylesheet" href="/static/css/csv_rollback.css">
@endpush

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-history"></i> CSV Import Rollback
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="la la-check-circle"></i> {{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if (session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="la la-exclamation-circle"></i> {{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Info Box --}}
        <div class="alert alert-info bg-light rounded p-4 mb-4 csv-upload-info-alert">
            <h5 class="text-info mb-3 fw-bold w-100">
                <i class="la la-info-circle"></i> Informazioni Rollback
            </h5>
            <ul class="mb-0 ps-0 list-unstyled">
                <li class="mb-2">
                    <i class="la la-check-circle text-success me-2"></i>
                    I backup vengono creati automaticamente prima di ogni import CSV
                </li>
                <li class="mb-2">
                    <i class="la la-check-circle text-success me-2"></i>
                    Il ripristino sovrascrive i dati attuali con quelli del backup selezionato
                </li>
                <li>
                    <i class="la la-exclamation-triangle text-warning me-2"></i>
                    Vengono conservati solo gli ultimi 2 backup per tabella
                </li>
            </ul>
        </div>

        {{-- Backups Card --}}
        <div class="card">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="la la-database"></i> Backup Disponibili
                </h3>
            </div>
            <div class="card-body p-0">
                @if (count($backups) === 0)
                    <div class="p-5 text-center text-muted">
                        <i class="la la-folder-open la-3x mb-3 d-block"></i>
                        <p class="mb-1">Nessun backup disponibile.</p>
                        <small>I backup vengono creati automaticamente prima di ogni import CSV.</small>
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover mb-0 csv-rollback-table">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 100px;">Tipo</th>
                                    <th style="width: 150px;">Tabella</th>
                                    <th>File</th>
                                    <th style="width: 100px;">Dimensione</th>
                                    <th style="width: 180px;">Data Backup</th>
                                    <th style="width: 120px;" class="text-end">Azioni</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($backups as $backup)
                                    <tr>
                                        <td>
                                            @php
                                                $typeStyles = [
                                                    'backend' => 'bg-danger text-white',
                                                    'job' => 'bg-warning text-white',
                                                ];
                                                $style = $typeStyles[$backup['type']] ?? 'bg-secondary text-white';
                                            @endphp
                                            <span class="badge {{ $style }}">{{ ucfirst($backup['type']) }}</span>
                                        </td>
                                        <td>
                                            <strong>{{ $backup['table'] }}</strong>
                                            @if($backup['table'] === 'users')
                                                <span class="badge bg-warning-subtle text-warning-emphasis users-warning-badge pulse-animation ms-1"
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top" 
                                                      title="Il ripristino della tabella users disconnetterà tutti gli utenti attualmente loggati, incluso te stesso.">
                                                    <i class="la la-exclamation-triangle"></i>
                                                </span>
                                            @endif
                                        </td>
                                        <td>
                                            <code class="text-muted">{{ $backup['filename'] }}</code>
                                        </td>
                                        <td class="text-muted" style="width: 20%; font-size: 0.8rem;">
                                            @php
                                                $sizeUnits = ['B', 'KB', 'MB', 'GB'];
                                                $sizeBytes = $backup['size'];
                                                $sizePow = floor(($sizeBytes ? log($sizeBytes) : 0) / log(1024));
                                                $sizePow = min($sizePow, count($sizeUnits) - 1);
                                                $sizeBytes /= pow(1024, $sizePow);
                                                $formattedSize = round($sizeBytes, 1) . ' ' . $sizeUnits[$sizePow];
                                            @endphp
                                            {{ $formattedSize }}
                                        </td>
                                        <td class="text-muted" style="width: 20%; font-size: 0.8rem;">
                                            {{ \Carbon\Carbon::createFromTimestamp($backup['modified'])->format('d/m/Y H:i:s') }}
                                        </td>
                                        <td class="text-end">
                                            <a href="{{ route('csv-rollback.preview', ['file' => $backup['file']]) }}"
                                               class="btn btn-sm btn-outline-primary" title="Anteprima">
                                                <i class="la la-eye"></i> Anteprima
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        {{-- Legend --}}
        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="la la-tags"></i> Legenda Tipi di Backup
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 120px;">Tipo</th>
                                <th>Descrizione</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td><span class="badge bg-danger text-white">Backend</span></td>
                                <td>Import eseguiti manualmente dal pannello Backpack</td>
                            </tr>
                            <tr>
                                <td><span class="badge bg-warning text-white">Job</span></td>
                                <td>Import eseguiti automaticamente da job schedulati</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    });
</script>
@endpush
