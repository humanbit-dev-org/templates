@extends(backpack_view('blank'))

@section('content')
<div class="container-fluid log-page">
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="la la-file-text"></i> {{ trans('backpack::base.logs') }}
                        @if($lastGlobalUpdate ?? false)
                            <small class="text-muted ms-2" style="font-size: 0.7rem; font-weight: normal;">
                                {{ trans('backpack::base.last_updated') }}: {{ \Carbon\Carbon::createFromTimestamp($lastGlobalUpdate)->locale('it')->isoFormat('D MMMM YYYY, HH:mm') }}
                            </small>
                        @endif
                    </h3>
                </div>
                
                <div class="card-body">
                    @if($error)
                        <div class="alert alert-warning">
                            <i class="la la-exclamation-triangle"></i> {{ $error }}
                        </div>
                        <div class="mt-3">
                            <a href="{{ url('admin/logs') }}" class="btn btn-secondary">
                                <i class="la la-arrow-left"></i> Torna indietro
                            </a>
                        </div>
                    @else
                        <!-- File Selection and Info -->
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <div class="d-flex align-items-center p-3 bg-light border rounded" style="gap: 0.5rem;">
                                    <!-- File Selector -->
                                    <div>
                                        <label class="mb-1 d-block small text-muted"><strong>{{ trans('backpack::base.log_file') }}</strong></label>
                                        @php
                                            // Raggruppa i log per cartella (root o sottocartelle)
                                            $groupedLogs = [];
                                            foreach ($logFiles as $file) {
                                                $dir = strpos($file['name'], '/') !== false ? dirname($file['name']) : 'root';
                                                $groupedLogs[$dir][] = $file;
                                            }
                                        @endphp
                                        <form method="GET" id="fileForm">
                                            <select name="file" class="form-select form-control" onchange="this.form.submit()" style="cursor: unset; min-width: 320px;">
                                                @foreach($groupedLogs as $dir => $files)
                                                    <optgroup label="{{ $dir === 'root' ? 'root' : $dir }}">
                                                        @foreach($files as $file)
                                                            @php
                                                                $label = basename($file['name']);
                                                                $pathInfo = $dir !== 'root' ? " ({$dir})" : '';
                                                            @endphp
                                                            <option value="{{ $file['name'] }}" {{ $selectedFile == $file['name'] ? 'selected' : '' }}>
                                                                {{ $label }}{{ $pathInfo }}
                                                            </option>
                                                        @endforeach
                                                    </optgroup>
                                                @endforeach
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <!-- File Info - Compact -->
                                    @if($fileInfo)
                                        <div class="d-flex align-items-center" style="gap: 0.5rem; margin-top: 1.5rem;">
                                            <span class="small text-muted">
                                                <i class="la la-hdd"></i> {{ $fileInfo['size'] }}
                                            </span>
                                            <span class="text-muted">•</span>
                                            <span class="small text-muted">
                                                <i class="la la-list"></i> {{ number_format($fileInfo['lines']) }} {{ trans('backpack::base.lines') }}
                                            </span>
                                            <span class="text-muted">•</span>
                                            <span class="small text-muted">
                                                <i class="la la-clock"></i> {{ \Carbon\Carbon::parse($fileInfo['modified'])->locale('it')->isoFormat('D MMM YYYY, HH:mm') }}
                                            </span>
                                        </div>
                                        
                                        <!-- Export Button (conditional) -->
                                        @if($selectedLevel || $searchQuery)
                                            @php
                                                $exportParams = http_build_query(array_filter([
                                                    'file' => $selectedFile,
                                                    'level' => $selectedLevel ?? '',
                                                    'search' => $searchQuery ?? ''
                                                ]));
                                            @endphp
                                            <a href="{{ url('admin/logs/export') }}?{{ $exportParams }}" class="btn btn-sm ms-2" style="background-color: var(--tblr-success); color: white; margin-top: 1.5rem;">
                                                <i class="la la-download"></i> {{ trans('backpack::base.export_log') }}
                                            </a>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        </div>
                        
                        <!-- Filters -->
                        <div class="card mb-3" style="background-color: #f8f9fa; border: 1px solid #dee2e6;">
                            <div class="card-body p-3">
                                <form method="GET" id="filterForm">
                                    <input type="hidden" name="file" value="{{ $selectedFile }}">
                                    <input type="hidden" name="perPage" value="{{ $perPage }}">
                                    
                                    <div class="row g-3">
                                        <!-- Level Filter -->
                                        <div class="col-md-3">
                                            <label class="small text-muted d-block mb-1"><strong>{{ trans('backpack::base.level_filter') }}</strong></label>
                                            <select name="level" class="form-select form-control form-control-sm" onchange="this.form.submit()" style="cursor: unset;">
                                                <option value="">{{ trans('backpack::base.all_levels') }}</option>
                                                <option value="DEBUG" {{ $selectedLevel == 'DEBUG' ? 'selected' : '' }}>Debug</option>
                                                <option value="INFO" {{ $selectedLevel == 'INFO' ? 'selected' : '' }}>Info</option>
                                                <option value="WARNING" {{ $selectedLevel == 'WARNING' ? 'selected' : '' }}>Warning</option>
                                                <option value="ERROR" {{ $selectedLevel == 'ERROR' ? 'selected' : '' }}>Error</option>
                                                <option value="CRITICAL" {{ $selectedLevel == 'CRITICAL' ? 'selected' : '' }}>Critical</option>
                                            </select>
                                        </div>
                                        
                                        <!-- Date From -->
                                        <div class="col-md-4">
                                            <label class="small text-muted d-block mb-1"><strong>Data inizio</strong></label>
                                            <input type="date" name="date_from" class="form-control form-control-sm" value="{{ $dateFrom ? (strlen($dateFrom) === 10 ? $dateFrom : explode('T', $dateFrom)[0]) : '' }}">
                                        </div>
                                        
                                        <!-- Date To -->
                                        <div class="col-md-4">
                                            <label class="small text-muted d-block mb-1"><strong>Data fine</strong></label>
                                            <input type="date" name="date_to" class="form-control form-control-sm" value="{{ $dateTo ? (strlen($dateTo) === 10 ? $dateTo : explode('T', $dateTo)[0]) : '' }}">
                                        </div>
                                        
                                        <!-- Search -->
                                        <div class="col-md-12">
                                            <label class="small text-muted d-block mb-1"><strong>{{ trans('backpack::base.search') }}</strong></label>
                                            <div class="input-group input-group-sm">
                                                <input type="text" name="search" class="form-control" placeholder="{{ trans('backpack::base.search_placeholder') }}" value="{{ $searchQuery ?? '' }}" autocomplete="off" spellcheck="false">
                                                <button class="btn btn-sm" type="submit" style="background-color: var(--tblr-primary); color: white;">
                                                    <i class="la la-search"></i> {{ trans('backpack::base.search') }}
                                                </button>
                                                @if($searchQuery ?? false || ($dateFrom ?? false) || ($dateTo ?? false))
                                                    @php
                                                        $clearParams = http_build_query(array_filter([
                                                            'file' => $selectedFile,
                                                            'level' => $selectedLevel ?? '',
                                                            'perPage' => $request->get('perPage', 10)
                                                        ]));
                                                    @endphp
                                                    <a href="?{{ $clearParams }}" class="btn btn-sm" style="background-color: var(--tblr-danger); color: white;">
                                                        <i class="la la-times"></i> {{ trans('backpack::base.clear') }}
                                                    </a>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                        
                        <!-- Logs Table -->
                        @if(count($logs) > 0)
                            <!-- Top Pagination -->
                            @php
                                $totalPages = ceil($total / ($perPage === 999999 ? $total : $perPage));
                            @endphp
                            
                            <div class="mb-3 pb-2 border-bottom">
                                <!-- Left: Per page selector -->
                                <div>
                                    <span class="text-muted me-1 small">{{ trans('backpack::base.show') }}</span>
                                        <form method="GET" class="d-inline-block">
                                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                                            <input type="hidden" name="level" value="{{ $selectedLevel ?? '' }}">
                                            <input type="hidden" name="search" value="{{ $searchQuery ?? '' }}">
                                            <select name="perPage" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto; display: inline-block; padding: 0.15rem 1.5rem 0.15rem 0.4rem; font-size: 0.8rem;">
                                                <option value="5" {{ $perPage == 5 ? 'selected' : '' }}>5</option>
                                                <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                                <option value="999999" {{ $perPage == 999999 ? 'selected' : '' }}>{{ strtoupper(trans('backpack::base.all')) }}</option>
                                            </select>
                                        </form>

                                    <!-- Right: Showing info and Pagination -->
                                    @php
                                        $startEntry = count($logs) > 0 ? (($currentPage - 1) * ($perPage === 999999 ? $total : $perPage)) + 1 : 0;
                                        $endEntry = $perPage === 999999 ? $total : min($currentPage * $perPage, $total);
                                        // Extract the word after _TOTAL_ from the translation (e.g., "record" or "entries")
                                        $infoTemplate = trans('backpack::crud.info');
                                        $entryWord = trim(str_replace('_TOTAL_', '', $infoTemplate));
                                    @endphp
                            
                                    <nav class="d-inline-block ms-3">
                                        @if($perPage !== 999999 && $total > $perPage)
                                            <span class="text-muted small me-2" style="white-space: nowrap;">
                                                {{ number_format($startEntry) }} - {{ number_format($endEntry) }} {{ $entryWord }} ({{ str_replace('_TOTAL_', number_format($total), trans('backpack::crud.info')) }})
                                            </span>
                                            <ul class="pagination pagination-sm mb-0">
                                                @php
                                                    $params = http_build_query(array_filter([
                                                        'file' => $selectedFile,
                                                        'level' => $selectedLevel ?? '',
                                                        'search' => $searchQuery ?? '',
                                                        'perPage' => $perPage
                                                    ]));
                                                @endphp
                                                
                                                @if($currentPage > 1)
                                                    <li class="page-item">
                                                        <a class="page-link" href="?{{ $params }}&page={{ $currentPage - 1 }}">
                                                            <i class="la la-angle-left"></i>
                                                        </a>
                                                    </li>
                                                @else
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="la la-angle-left"></i></span>
                                                    </li>
                                                @endif
                                                
                                                @for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                                                    <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                                        <a class="page-link" href="?{{ $params }}&page={{ $i }}">
                                                            {{ $i }}
                                                        </a>
                                                    </li>
                                                @endfor
                                                
                                                @if($currentPage < $totalPages)
                                                    <li class="page-item">
                                                        <a class="page-link" href="?{{ $params }}&page={{ $currentPage + 1 }}">
                                                            <i class="la la-angle-right"></i>
                                                        </a>
                                                    </li>
                                                @else
                                                    <li class="page-item disabled">
                                                        <span class="page-link"><i class="la la-angle-right"></i></span>
                                                    </li>
                                                @endif
                                            </ul>
                                        @else
                                            <span class="text-muted small">
                                                {{ str_replace('_TOTAL_', number_format($total), trans('backpack::crud.info')) }}
                                            </span>
                                        @endif
                                    </nav>
                                </div>
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead>
                                        <tr>
                                            <th style="width: 180px;">Timestamp</th>
                                            <th style="width: 100px;">Level</th>
                                            <th style="width: 100px;">Environment</th>
                                            <th>Message</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($logs as $log)
                                            <tr class="log-entry log-{{ strtolower($log['level']) }} {{ isset($log['grouped']) && $log['grouped'] ? 'log-grouped' : '' }}">
                                                <td style="vertical-align: top;">
                                                    <small>{{ \Carbon\Carbon::parse($log['timestamp'])->locale('it')->isoFormat('D MMM YYYY, HH:mm:ss') }}</small>
                                                </td>
                                                <td style="vertical-align: top;">
                                                    @php
                                                        $badgeStyle = match($log['level']) {
                                                            'DEBUG' => 'background-color: #6c757d; color: white;',
                                                            'INFO' => 'background-color: #0dcaf0; color: white;',
                                                            'WARNING' => 'background-color: #ffc107; color: #000;',
                                                            'ERROR' => 'background-color: #dc3545; color: white;',
                                                            'CRITICAL' => 'background-color: #dc3545; color: white;',
                                                            default => 'background-color: #f8f9fa; color: #000;'
                                                        };
                                                    @endphp
                                                    <span class="badge" style="{{ $badgeStyle }}">
                                                        {{ $log['level'] }}
                                                    </span>
                                                    @if(isset($log['grouped']) && $log['grouped'])
                                                        <br><span class="badge badge-light mt-1" style="font-size: 0.7rem;">
                                                            <i class="la la-layer-group"></i> grouped
                                                        </span>
                                                    @endif
                                                </td>
                                                <td style="vertical-align: top;">
                                                    <small class="text-muted">{{ $log['environment'] }}</small>
                                                </td>
                                                <td>
                                                    <div class="log-message">
                                                        @if(isset($log['is_json']) && $log['is_json'])
                                                            <div class="json-viewer" style="max-height: 300px; overflow-y: auto;">
                                                                <pre style="white-space: pre-wrap; font-size: 12px; margin: 0; color: #2d3748; background: #f8f9fa; padding: 8px; border-radius: 4px; border: 1px solid #dee2e6;">{{ $log['message'] }}</pre>
                                                            </div>
                                                        @else
                                                            <pre style="white-space: pre-wrap; font-size: 12px; margin: 0; max-height: 300px; overflow-y: auto; color: #2d3748;">{{ $log['message'] }}</pre>
                                                        @endif
                                                    </div>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Bottom Pagination (same as top) -->
                            @if($total > 0)
                                <div class="mt-4 pt-3 border-top">
                                    <!-- Left: Per page selector -->
                                    <div>
                                        <span class="text-muted me-1 small">{{ trans('backpack::base.show') }}</span>
                                        <form method="GET" class="d-inline-block">
                                            <input type="hidden" name="file" value="{{ $selectedFile }}">
                                            <input type="hidden" name="level" value="{{ $selectedLevel ?? '' }}">
                                            <input type="hidden" name="search" value="{{ $searchQuery ?? '' }}">
                                            <select name="perPage" class="form-select form-select-sm" onchange="this.form.submit()" style="width: auto; display: inline-block; padding: 0.15rem 1.5rem 0.15rem 0.4rem; font-size: 0.8rem;">
                                                <option value="5" {{ $perPage == 5 ? 'selected' : '' }}>5</option>
                                                <option value="10" {{ $perPage == 10 ? 'selected' : '' }}>10</option>
                                                <option value="25" {{ $perPage == 25 ? 'selected' : '' }}>25</option>
                                                <option value="50" {{ $perPage == 50 ? 'selected' : '' }}>50</option>
                                                <option value="100" {{ $perPage == 100 ? 'selected' : '' }}>100</option>
                                                <option value="999999" {{ $perPage == 999999 ? 'selected' : '' }}>{{ strtoupper(trans('backpack::base.all')) }}</option>
                                            </select>
                                        </form>

                                        <!-- Right: Showing info and Pagination -->
                                        @php
                                            $startEntry = count($logs) > 0 ? (($currentPage - 1) * ($perPage === 999999 ? $total : $perPage)) + 1 : 0;
                                            $endEntry = $perPage === 999999 ? $total : min($currentPage * $perPage, $total);
                                            // Extract the word after _TOTAL_ from the translation (e.g., "record" or "entries")
                                            $infoTemplate = trans('backpack::crud.info');
                                            $entryWord = trim(str_replace('_TOTAL_', '', $infoTemplate));
                                        @endphp
                                
                                        <nav class="d-inline-block ms-3">
                                            @if($perPage !== 999999 && $total > $perPage)
                                                <span class="text-muted small me-2" style="white-space: nowrap;">
                                                    {{ number_format($startEntry) }} - {{ number_format($endEntry) }} {{ $entryWord }} ({{ str_replace('_TOTAL_', number_format($total), trans('backpack::crud.info')) }})
                                                </span>
                                                <ul class="pagination pagination-sm mb-0">
                                                    @php
                                                        $params = http_build_query(array_filter([
                                                            'file' => $selectedFile,
                                                            'level' => $selectedLevel ?? '',
                                                            'search' => $searchQuery ?? '',
                                                            'perPage' => $perPage
                                                        ]));
                                                    @endphp
                                                    
                                                    @if($currentPage > 1)
                                                        <li class="page-item">
                                                            <a class="page-link" href="?{{ $params }}&page={{ $currentPage - 1 }}">
                                                                <i class="la la-angle-left"></i>
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li class="page-item disabled">
                                                            <span class="page-link"><i class="la la-angle-left"></i></span>
                                                        </li>
                                                    @endif
                                                    
                                                    @for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++)
                                                        <li class="page-item {{ $i == $currentPage ? 'active' : '' }}">
                                                            <a class="page-link" href="?{{ $params }}&page={{ $i }}">
                                                                {{ $i }}
                                                            </a>
                                                        </li>
                                                    @endfor
                                                    
                                                    @if($currentPage < $totalPages)
                                                        <li class="page-item">
                                                            <a class="page-link" href="?{{ $params }}&page={{ $currentPage + 1 }}">
                                                                <i class="la la-angle-right"></i>
                                                            </a>
                                                        </li>
                                                    @else
                                                        <li class="page-item disabled">
                                                            <span class="page-link"><i class="la la-angle-right"></i></span>
                                                        </li>
                                                    @endif
                                                </ul>
                                            @else
                                                <span class="text-muted small">
                                                    {{ str_replace('_TOTAL_', number_format($total), trans('backpack::crud.info')) }}
                                                </span>
                                            @endif
                                        </nav>
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="alert alert-info">
                                <i class="la la-info-circle"></i> No logs found with the selected filters.
                            </div>
                        @endif
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

