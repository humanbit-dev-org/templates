@extends(backpack_view('blank'))

@push('after_styles')
<link rel="stylesheet" href="/static/css/logs_index.css">
@endpush

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-file-text"></i> {{ trans('backpack::base.logs') }}
        @if($lastGlobalUpdate ?? false)
            <small class="text-muted ms-2" style="font-size: 0.7rem; font-weight: normal;">
                {{ trans('backpack::base.last_updated') }}: {{ \Carbon\Carbon::createFromTimestamp($lastGlobalUpdate)->timezone('Europe/Rome')->locale('it')->isoFormat('D MMMM YYYY, HH:mm') }}
            </small>
        @endif
    </h1>
</section>
@endsection

@section('content')
<div class="container-fluid log-page">
    @if(!empty($logFiles))
        <div class="d-flex justify-content-end mb-3">
            <button type="button"
                    class="btn btn-sm btn-outline-danger"
                    onclick="deleteAllLogs()">
                <i class="la la-trash"></i> {{ trans('backpack::base.delete_all_logs') }}
            </button>
        </div>
    @endif

    <div class="alert alert-secondary mb-3" style="background-color: #f8f9fa; border-color: #dee2e6; color: #495057;">
        <i class="la la-info-circle me-1"></i>
        Questa sezione permette di ispezionare i log, operazione non possibile su filesystem, dal momento che vengono cifrati durante la scrittura.
    </div>

    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="la la-check-circle"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="la la-exclamation-circle"></i> {{ session('error') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(empty($logFiles))
        <div class="alert alert-warning">
            <i class="la la-exclamation-triangle"></i> Nessun file di log trovato
        </div>
    @else
        @php
            // Group logs by base path + first subfolder only (ignore timestamp folders)
            $groupedLogs = [];
            foreach ($logFiles as $file) {
                $relativePath = $file['name'];
                
                // Determine base path and extract first meaningful subfolder
                if (strpos($relativePath, 'storage/logs/imports/') === 0) {
                    // storage/logs/imports/users/2026-03-01_19-21-59/file.json → storage/logs/imports/users
                    $afterBase = substr($relativePath, strlen('storage/logs/imports/'));
                    $firstFolder = explode('/', $afterBase)[0] ?? '';
                    $groupKey = 'storage/logs/imports' . ($firstFolder ? '/' . $firstFolder : '');
                } elseif (strpos($relativePath, 'storage/logs/') === 0) {
                    // storage/logs/laravel.log → storage/logs
                    $groupKey = 'storage/logs';
                } elseif (strpos($relativePath, 'database/import/') === 0) {
                    // database/import/user/file.log → database/import/user
                    $afterBase = substr($relativePath, strlen('database/import/'));
                    $firstFolder = explode('/', $afterBase)[0] ?? '';
                    $groupKey = 'database/import' . ($firstFolder ? '/' . $firstFolder : '');
                } else {
                    $groupKey = 'storage/logs';
                }
                
                if (!isset($groupedLogs[$groupKey])) {
                    $groupedLogs[$groupKey] = [];
                }
                $groupedLogs[$groupKey][] = $file;
            }
            
            // Sort groups alphabetically
            ksort($groupedLogs);
        @endphp

        @foreach($groupedLogs as $groupKey => $files)
            @php
                // Extract the last part of the path for bold display
                $pathParts = explode('/', $groupKey);
                $lastPart = array_pop($pathParts);
                $basePath = implode('/', $pathParts);
            @endphp
            <div class="card mb-3">
                <div class="card-header py-2">
                    <div class="d-flex align-items-center justify-content-between log-group-header">
                        <div class="d-flex align-items-center log-group-meta">
                            <i class="la la-folder-open text-muted me-2"></i>
                            <code class="log-group-path">
                                <span class="text-muted">{{ $basePath }}/</span><strong class="text-dark">{{ $lastPart }}</strong>
                            </code>
                            <span class="badge bg-secondary text-white ms-2">{{ count($files) }}</span>
                        </div>
                        <button type="button"
                                class="btn btn-sm btn-outline-danger log-group-delete-btn"
                                onclick="event.stopPropagation(); deleteLogGroup('{{ $groupKey }}')">
                            <i class="la la-trash"></i> {{ trans('backpack::base.delete_group') }}
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <table class="table table-sm table-hover mb-0">
                        <tbody>
                            @foreach($files as $file)
                                @php
                                    $fileName = $file['name'];
                                    $fileSize = $file['size'];
                                    $fileModified = $file['modified'];
                                    
                                    // Show relative path from group (e.g., "2026-03-01_19-21-59/summary.json")
                                    $displayPath = substr($fileName, strlen($groupKey) + 1);
                                    
                                    $sizeUnits = ['B', 'KB', 'MB', 'GB'];
                                    $sizeBytes = $fileSize;
                                    $sizePow = floor(($sizeBytes ? log($sizeBytes) : 0) / log(1024));
                                    $sizePow = min($sizePow, count($sizeUnits) - 1);
                                    $sizeBytes /= pow(1024, $sizePow);
                                    $formattedSize = round($sizeBytes, 1) . ' ' . $sizeUnits[$sizePow];
                                    
                                    $isLarge = $fileSize > 5 * 1024 * 1024;
                                @endphp
                                <tr class="log-row" data-href="{{ url('admin/logs/show') }}?file={{ urlencode($fileName) }}">
                                    <td class="ps-3" style="width: 50%;">
                                        <i class="la la-file-alt text-primary me-1"></i>
                                        <span class="file-name">{{ $displayPath }}</span>
                                    </td>
                                    <td class="text-muted" style="width: 15%; font-size: 0.8rem;">
                                        @if($isLarge)
                                            <span class="text-warning" title="File grande">{{ $formattedSize }}</span>
                                        @else
                                            {{ $formattedSize }}
                                        @endif
                                    </td>
                                    <td class="text-muted" style="width: 20%; font-size: 0.8rem;">
                                        {{ \Carbon\Carbon::createFromTimestamp($fileModified)->timezone('Europe/Rome')->format('d/m/Y H:i') }}
                                    </td>
                                    <td class="text-end pe-3" style="width: 15%;">
                                        <div class="btn-group btn-group-sm">
                                            <a href="{{ url('admin/logs/export') }}?file={{ urlencode($fileName) }}" 
                                               class="btn btn-outline-success btn-sm log-action-btn" title="Scarica"
                                               onclick="event.stopPropagation();">
                                                <i class="la la-download"></i>
                                            </a>
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm log-action-btn"
                                                    onclick="event.stopPropagation(); deleteLogFile('{{ $fileName }}')"
                                                    title="Elimina">
                                                <i class="la la-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach
    @endif
</div>

<script>
document.querySelectorAll('.log-row').forEach(row => {
    row.addEventListener('click', function() {
        window.location.href = this.dataset.href;
    });
});

function deleteLogFile(fileName) {
    if (!confirm('Eliminare questo file di log?')) {
        return;
    }
    
    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("admin/logs/delete") }}';
    
    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);
    
    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'file';
    input.value = fileName;
    form.appendChild(input);
    
    document.body.appendChild(form);
    form.submit();
}

function deleteLogGroup(groupKey) {
    if (!confirm(@json(trans('backpack::base.confirm_delete_group')))) {
        return;
    }

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("admin/logs/delete-group") }}';

    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    var input = document.createElement('input');
    input.type = 'hidden';
    input.name = 'group';
    input.value = groupKey;
    form.appendChild(input);

    document.body.appendChild(form);
    form.submit();
}

function deleteAllLogs() {
    if (!confirm(@json(trans('backpack::base.confirm_delete_all_logs')))) {
        return;
    }

    var form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ url("admin/logs/delete-all") }}';

    var csrf = document.createElement('input');
    csrf.type = 'hidden';
    csrf.name = '_token';
    csrf.value = '{{ csrf_token() }}';
    form.appendChild(csrf);

    document.body.appendChild(form);
    form.submit();
}
</script>
@endsection
