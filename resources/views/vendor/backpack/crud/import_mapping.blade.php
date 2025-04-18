@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        {{ trans('backpack::import.configure_import', ['name' => $crud]) }}
    </h1>
</section>
@endsection

@section('content')
<div class="row" bp-section="crud-operation-import">
    <div class="col-md-12">
        <!-- Anteprima del file CSV -->
        <div class="accordion mb-3" id="csvPreviewAccordion">
            <div class="accordion-item">
                <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#csvPreviewCollapse" aria-expanded="false" aria-controls="csvPreviewCollapse">
                        <i class="la la-table me-1"></i> {{ trans('backpack::import.csv_preview') }}
                    </button>
                </h2>
                <div id="csvPreviewCollapse" class="accordion-collapse collapse" data-bs-parent="#csvPreviewAccordion">
                    <div class="accordion-body p-0">
                        <div class="csv-preview-container" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-sm table-striped mb-0">
                                <thead>
                                    <tr>
                                        @foreach($csvHeaders as $header)
                                        <th class="csv-header">
                                            @if(Str::length($header) > 30 || Str::endsWith(Str::limit($header, 30), '...'))
                                            <span class="truncated-text" data-full-text="{{ $header }}">{{ Str::limit($header, 30) }}</span>
                                            @else
                                            {{ $header }}
                                            @endif
                                        </th>
                                        @endforeach
                                    </tr>
                                </thead>
                                <tbody>
                                    @if(isset($csvPreview) && count($csvPreview) > 0)
                                    @foreach($csvPreview as $row)
                                    <tr>
                                        @foreach($row as $value)
                                        <td class="csv-cell">
                                            @if(Str::length($value) > 50 || Str::endsWith(Str::limit($value, 50), '...'))
                                            <span class="truncated-text" data-full-text="{{ $value }}">{{ Str::limit($value, 50) }}</span>
                                            @else
                                            {{ $value }}
                                            @endif
                                        </td>
                                        @endforeach
                                    </tr>
                                    @endforeach
                                    @else
                                    <tr>
                                        <td colspan="{{ count($csvHeaders) }}" class="text-center py-3 text-muted">
                                            <i class="la la-info-circle"></i> {{ trans('backpack::import.no_preview_data') }}
                                        </td>
                                    </tr>
                                    @endif
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Campo Unique Field in rilievo -->
        <div class="card mb-3 border-primary unique-field-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="la la-key text-primary fs-3 me-2"></i>
                    <h4 class="mb-0 fw-bold">{{ trans('backpack::import.unique_field') }}</h4>
                </div>

                <select name="unique_field" id="unique_field" class="form-control form-select">
                    <option value="">{{ trans('backpack::import.no_unique_field') }}</option>
                    <optgroup label="{{ trans('backpack::import.table_field') }}">
                        @foreach($tableColumns as $column)
                        <option value="{{ $column }}">{{ $column }}</option>
                        @endforeach
                    </optgroup>
                </select>
            </div>
        </div>

        <!-- Mappatura colonne CSV -->
        <div class="card import-card">
            <div class="card-header border-bottom">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <h3 class="card-title mb-0">
                        <i class="la la-exchange-alt me-1"></i>
                        {{ trans('backpack::import.column_mapping') }}
                    </h3>
                    <div>
                        <button type="button" id="auto-map-btn" class="btn btn-primary">
                            <i class="la la-magic me-1"></i> {{ trans('backpack::import.auto_map') }}
                        </button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <form id="import-mapping-form" class="import-form" method="POST" action="{{ url($crud_route.'/import-csv/process') }}">
                    @csrf
                    <input type="hidden" name="file_path" value="{{ $filePath }}">
                    <input type="hidden" name="delimiter" value="{{ $delimiter }}">

                    <div class="alert alert-info mb-4">
                        <i class="la la-info-circle me-1"></i>
                        {{ trans('backpack::import.mapping_instructions') }}
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover mapping-table">
                            <thead class="table-light">
                                <tr>
                                    <th width="3%">#</th>
                                    <th width="47%">{{ trans('backpack::import.table_field') }}</th>
                                    <th width="50%">{{ trans('backpack::import.csv_column') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tableColumns as $columnIndex => $column)
                                <tr class="mapping-row" data-table-column="{{ $column }}">
                                    <td class="text-center align-middle column-index-cell">
                                        <span class="badge bg-primary-subtle text-primary-emphasis column-index">{{ $columnIndex + 1 }}</span>
                                    </td>
                                    <td class="align-middle table-column">
                                        <span class="fw-medium">{{ $column }}</span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center position-relative">
                                            <span class="mapping-type-indicator" data-column="{{ $column }}"></span>
                                            <select name="column_mapping_reverse[{{ $column }}]" class="form-select field-mapping-select csv-field-select">
                                                <option value="">{{ trans('backpack::import.do_not_import') }}</option>
                                                <optgroup label="{{ trans('backpack::import.csv_column') }}">
                                                    @foreach($csvHeaders as $index => $header)
                                                    <option value="{{ $index }}" data-csv-header="{{ $header }}" class="csv-option">
                                                        {{ $header }}
                                                    </option>
                                                    @endforeach
                                                </optgroup>
                                            </select>
                                        </div>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="form-group mt-4 import-form-actions">
                        <a href="{{ url($crud_route) }}" class="btn btn-outline-secondary import-cancel-btn">
                            <i class="la la-times"></i>
                            {{ trans('backpack::import.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary import-submit-btn" id="start-import">
                            <i class="la la-play"></i>
                            {{ trans('backpack::import.start_import') }}
                        </button>
                    </div>
                </form>

                <div id="import-progress" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <h4 class="text-primary mb-3">
                            <i class="la la-sync fa-spin me-2"></i>
                            {{ trans('backpack::import.import_in_progress') }}
                        </h4>
                        <div class="progress mb-3">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>

                        <div class="mt-3">
                            <div class="progress-percentage mb-3 text-center">
                                <span id="progress-text" class="badge bg-primary fs-5">0%</span>
                            </div>
                            <div id="import-stats" class="stats-container mt-3">
                                <div class="row">
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.processed_rows') }}</div>
                                                <div class="h3" id="total-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.new_records') }}</div>
                                                <div class="h3 text-success" id="created-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.updated_records') }}</div>
                                                <div class="h3 text-primary" id="updated-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-sm-6 col-md-3 mb-2">
                                        <div class="card border-0 shadow-sm h-100">
                                            <div class="card-body text-center">
                                                <div class="h5 text-muted mb-2">{{ trans('backpack::import.skipped_rows') }}</div>
                                                <div class="h3 text-warning" id="skipped-rows">0</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="import-results" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <h4 class="text-success mb-3">
                            <i class="la la-check-circle me-2"></i>
                            {{ trans('backpack::import.import_completed') }}
                        </h4>

                        <!-- Barra di progresso al 100% -->
                        <div class="progress mb-4">
                            <div class="progress-bar bg-success" role="progressbar" style="width: 100%" aria-valuenow="100" aria-valuemin="0" aria-valuemax="100"></div>
                        </div>

                        <div class="mt-3 mb-3 text-center">
                            <span class="badge bg-success fs-5">100%</span>
                        </div>

                        <div id="result-stats" class="stats-container mt-3">
                            <div class="row">
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.processed_rows') }}</div>
                                            <div class="h3" id="result-total-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.new_records') }}</div>
                                            <div class="h3 text-success" id="result-created-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.updated_records') }}</div>
                                            <div class="h3 text-primary" id="result-updated-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">{{ trans('backpack::import.skipped_rows') }}</div>
                                            <div class="h3 text-warning" id="result-skipped-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="la la-info-circle me-2"></i>
                            <span>
                                {{ trans('backpack::import.backup_created') }}
                                <code>/storage/app/import-backups/</code>
                                {{ trans('backpack::import.backup_name') }}
                                <strong id="backup-filename" class="fw-bold"></strong>
                            </span>
                        </div>

                        <div class="text-center mt-4">
                            <a href="{{ url($crud_route) }}" class="btn btn-success">
                                <i class="la la-table me-1"></i>
                                {{ trans('backpack::import.back_to_list') }}
                            </a>
                        </div>
                    </div>
                </div>

                <div id="import-error" style="display: none;">
                    <div class="alert alert-danger">
                        <h4 class="alert-heading">
                            <i class="la la-exclamation-circle me-2"></i>
                            {{ trans('backpack::import.import_error') }}
                        </h4>
                        <p id="error-message" class="mb-3"></p>
                        <div id="error-details" class="border rounded bg-light p-3 mb-3" style="display: none;">
                            <h6 class="text-danger"><i class="la la-bug"></i> Dettagli tecnici dell'errore:</h6>
                            <pre id="error-log" class="small text-pre-wrap bg-dark text-light p-2 rounded" style="max-height: 200px; overflow-y: auto;"></pre>
                        </div>
                        <p class="mb-0">
                            {{ trans('backpack::import.backup_created') }}
                            <code>/storage/app/import-backups/</code>
                            {{ trans('backpack::import.backup_name') }}
                            <span id="error-backup-filename" class="fw-bold"></span>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tooltip container per mostrare il testo completo -->
<div class="content-tooltip" id="contentTooltip"></div>

<!-- Overlay per effetto Auto-Map -->
<div id="auto-map-overlay" class="auto-map-overlay">
    <div class="stars-container">
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
        <div class="tiny-star"></div>
    </div>
    <div class="auto-map-animation">
        <i class="la la-magic fa-spin"></i>
        <span>{{ trans('backpack::import.mapping_in_progress') }}</span>
    </div>
</div>
@endsection

@push('after_styles')
<style>
    /* Stile dell'accordion come in list.blade.php */
    .accordion-item {
        border: none !important;
        margin-bottom: 0.5rem;
        transition: all 0.3s ease;
        background-color: var(--color-accordion-bg);
        overflow: hidden;
    }

    .accordion-button {
        background-color: var(--color-accordion-button-bg);
        color: var(--color-accordion-button-text);
        font-weight: 500;
        border-radius: 4px;
        transition: all 0.25s ease;
        padding: 0.75rem 1.25rem;
        width: 100%;
        margin: 0;
        border: 1.5px solid rgba(var(--tblr-primary-rgb), 0.3) !important;
    }

    .accordion-button:hover {
        background-color: var(--color-accordion-button-hover-bg);
        color: var(--tblr-primary);
        box-shadow: 0 2px 8px rgba(var(--tblr-primary-rgb), 0.2);
    }

    .accordion-button.collapsed {
        opacity: 0.9;
        padding-top: 0.5rem;
        padding-bottom: 0.5rem;
        border-radius: 4px !important;
        border: 1.5px solid rgba(var(--tblr-primary-rgb), 0.3) !important;
    }

    .accordion-button:not(.collapsed) {
        background-color: var(--color-accordion-button-active-bg);
        color: var(--color-accordion-button-active-text);
        box-shadow: 0 3px 8px rgba(var(--tblr-primary-rgb), 0.25);
        padding: 0.75rem 1.25rem;
        border-bottom-left-radius: 0 !important;
        border-bottom-right-radius: 0 !important;
        border: 1.5px solid var(--tblr-primary) !important;
        border-bottom: none !important;
    }

    .accordion-collapse.show {
        border-top-left-radius: 0 !important;
        border-top-right-radius: 0 !important;
        border-bottom-left-radius: 4px !important;
        border-bottom-right-radius: 4px !important;
        border-left: 1.5px solid rgba(var(--tblr-primary-rgb), 0.3) !important;
        border-right: 1.5px solid rgba(var(--tblr-primary-rgb), 0.3) !important;
        border-bottom: 1.5px solid rgba(var(--tblr-primary-rgb), 0.3) !important;
    }

    /* Tabella anteprima CSV */
    .csv-preview-container {
        border-bottom: 1px solid rgba(var(--tblr-primary-rgb), 0.1);
    }

    .csv-preview-container thead {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: var(--tblr-light);
        border-bottom: 2px solid rgba(var(--tblr-primary-rgb), 0.1);
    }

    .csv-header {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
        padding: 0.75rem !important;
        font-weight: 600;
        color: var(--tblr-primary);
        background-color: rgba(var(--tblr-primary-rgb), 0.05);
    }

    .csv-cell {
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 200px;
        padding: 0.5rem 0.75rem !important;
    }

    /* Stile per i testi troncati con indicatore visivo migliorato */
    .truncated-text {
        position: relative;
        cursor: pointer;
        border-bottom: 1px dotted rgba(var(--tblr-primary-rgb), 0.5);
        color: var(--tblr-primary);
        transition: all 0.2s ease;
        padding: 1px 3px;
        border-radius: 3px;
        background-color: rgba(var(--tblr-primary-rgb), 0.05);
        /* Impedisce la selezione del testo */
        user-select: none;
        -webkit-user-select: none;
        -moz-user-select: none;
        -ms-user-select: none;
    }

    .truncated-text:hover {
        background-color: rgba(var(--tblr-primary-rgb), 0.1);
        border-bottom: 1px solid var(--tblr-primary);
        box-shadow: 0 1px 3px rgba(var(--tblr-primary-rgb), 0.2);
    }

    /* Stile per l'elemento attivo */
    .truncated-text-active {
        background-color: rgba(var(--tblr-primary-rgb), 0.2) !important;
        border-bottom: 1px solid var(--tblr-primary) !important;
        box-shadow: 0 1px 4px rgba(var(--tblr-primary-rgb), 0.3) !important;
        color: var(--tblr-primary-darker) !important;
        border-radius: 4px;
    }

    /* Tooltip per mostrare il contenuto completo */
    .content-tooltip {
        position: absolute;
        display: none;
        background-color: var(--tblr-bg-surface);
        border: 1px solid rgba(var(--tblr-primary-rgb), 0.2);
        border-radius: 6px;
        padding: 10px 14px;
        z-index: 1000;
        max-width: 400px;
        box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        font-size: 0.875rem;
        color: var(--tblr-body-color);
        word-break: break-word;
        white-space: normal;
        transform-origin: top left;
        opacity: 0;
        transform: scale(0.95);
        transition: opacity 0.2s ease, transform 0.2s ease;
    }

    .content-tooltip.show {
        opacity: 1;
        transform: scale(1);
    }

    /* Tabella di mapping migliorata */
    .mapping-table {
        border-collapse: separate;
        border-spacing: 0;
        box-shadow: 0 0 10px rgba(0, 0, 0, 0.03);
        border-radius: 8px;
        overflow: hidden;
        border: 1px solid rgba(var(--tblr-primary-rgb), 0.1);
    }

    .mapping-table thead th {
        position: sticky;
        top: 0;
        z-index: 10;
        background-color: var(--tblr-light);
        font-weight: 600;
        color: var(--tblr-primary);
        border-bottom: 2px solid rgba(var(--tblr-primary-rgb), 0.1);
    }

    .mapping-row {
        transition: all 0.2s ease;
        border-bottom: 1px solid rgba(var(--tblr-primary-rgb), 0.05);
    }

    .mapping-row:nth-child(odd) {
        background-color: rgba(var(--tblr-primary-rgb), 0.01);
    }

    .mapping-row:hover {
        background-color: rgba(var(--tblr-primary-rgb), 0.03);
    }

    /* Stile per la cella del numero di indice */
    .column-index-cell {
        width: 3%;
        padding: 0.5rem 0.25rem !important;
    }

    /* Stile fisso per il badge del numero di indice */
    .column-index {
        padding: 0.25rem 0.4rem;
        font-size: 0.75rem;
        font-weight: 600;
        min-width: 1.5rem;
        display: inline-block;
    }

    /* Miglioramento della cella della colonna tabella */
    .table-column {
        padding: 0.7rem 0.75rem !important;
        border-right: 1px solid rgba(var(--tblr-primary-rgb), 0.05);
    }

    /* Miglioramento della select box per il mapping */
    .csv-field-select {
        border: 1px solid rgba(var(--tblr-primary-rgb), 0.2);
        border-radius: 5px;
        padding: 0.4rem 0.75rem;
        transition: all 0.2s ease;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .csv-field-select:focus {
        border-color: var(--tblr-primary);
        box-shadow: 0 0 0 0.25rem rgba(var(--tblr-primary-rgb), 0.15);
    }

    /* Miglioramenti per i pulsanti di azione */
    .import-form-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 1.5rem;
    }

    .import-submit-btn {
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(var(--tblr-primary-rgb), 0.2);
    }

    .import-submit-btn:hover {
        box-shadow: 0 3px 8px rgba(var(--tblr-primary-rgb), 0.3);
        transform: translateY(-1px);
    }

    .import-cancel-btn {
        padding: 0.5rem 1.5rem;
    }

    /* Evidenzio solo il testo interno */
    .mapping-row .table-column .fw-medium.highlighted-field {
        background-color: rgba(var(--tblr-primary-rgb), 0.15);
        border-radius: 4px;
        font-weight: 700;
        color: var(--tblr-primary-darker);
        padding: 4px 8px;
        display: inline-block;
        position: relative;
        box-shadow: 0 0 8px rgba(var(--tblr-primary-rgb), 0.4);
        transform-origin: center;
        animation: pulse-highlight 2s infinite;
    }

    /* Card per Unique Field */
    .unique-field-card {
        background-color: rgba(var(--tblr-primary-rgb), 0.02);
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .unique-field-card:hover {
        background-color: rgba(var(--tblr-primary-rgb), 0.05);
        box-shadow: 0 4px 12px rgba(var(--tblr-primary-rgb), 0.1);
    }

    /* Punti di domanda informativi - stile migliorato */
    .mapping-info {
        display: none !important;
    }

    .mapping-info-warning {
        display: none !important;
    }

    .mapping-info-danger {
        display: none !important;
    }

    /* Tooltip per i punti di domanda */
    .mapping-info::after {
        display: none !important;
    }

    .mapping-info-warning::after {
        display: none !important;
    }

    .mapping-info-danger::after {
        display: none !important;
    }

    .mapping-info:hover::after {
        display: none !important;
    }

    /* Colori per Auto-Map - sfondo fisso */
    .field-mapping-select.mapping-match-exact {
        background-color: rgba(var(--tblr-success-rgb), 0.1) !important;
        border-color: rgba(var(--tblr-success-rgb), 0.7) !important;
        box-shadow: 0 0 0 0.25rem rgba(var(--tblr-success-rgb), 0.1) !important;
    }

    .field-mapping-select.mapping-match-similar {
        background-color: rgba(var(--tblr-warning-rgb), 0.1) !important;
        border-color: rgba(var(--tblr-warning-rgb), 0.7) !important;
        box-shadow: 0 0 0 0.25rem rgba(var(--tblr-warning-rgb), 0.1) !important;
    }

    .field-mapping-select.mapping-match-none {
        background-color: rgba(var(--tblr-danger-rgb), 0.1) !important;
        border-color: rgba(var(--tblr-danger-rgb), 0.7) !important;
        box-shadow: 0 0 0 0.25rem rgba(var(--tblr-danger-rgb), 0.1) !important;
    }

    /* Indicatori di tipo mapping */
    .mapping-type-indicator {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 3px;
        font-size: 0.7rem;
        font-weight: 400;
        line-height: 1.4;
        white-space: nowrap;
        transition: all 0.3s ease;
        opacity: 0;
        position: absolute;
        left: -190px;
        top: 50%;
        transform: translateY(-50%) scale(0.95);
        z-index: 5;
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        pointer-events: none;
        letter-spacing: 0.01em;
    }

    .mapping-type-indicator.show {
        opacity: 0.85;
        transform: translateY(-50%) scale(1);
    }

    .mapping-type-indicator i {
        margin-right: 3px;
        font-size: 0.65rem;
        opacity: 0.85;
    }

    .mapping-type-exact {
        background-color: rgba(var(--tblr-success-rgb), 0.12);
        color: rgba(0, 100, 0, 0.95);
        border: 1px solid rgba(var(--tblr-success-rgb), 0.2);
    }

    .mapping-type-similar {
        background-color: rgba(var(--tblr-warning-rgb), 0.12);
        color: rgba(140, 80, 0, 0.95);
        border: 1px solid rgba(var(--tblr-warning-rgb), 0.2);
    }

    .mapping-type-none {
        background-color: rgba(var(--tblr-danger-rgb), 0.12);
        color: rgba(165, 0, 0, 0.95);
        border: 1px solid rgba(var(--tblr-danger-rgb), 0.2);
    }

    /* Hover effect per le righe */
    .mapping-row {
        transition: all 0.3s ease;
    }

    .mapping-row:hover .mapping-type-indicator.hide-after-delay:not(.user-modified) {
        opacity: 0.85;
        transform: translateY(-50%) scale(1);
    }

    /* Overlay per Auto-Map */
    .auto-map-overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 9999;
        display: none;
        justify-content: center;
        align-items: center;
        overflow: hidden;
    }

    .auto-map-animation {
        background-color: white;
        padding: 2rem;
        border-radius: 8px;
        text-align: center;
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
        position: relative;
        z-index: 10;
        animation: float-animation 3s ease-in-out infinite;
    }

    @keyframes float-animation {
        0% {
            transform: translateY(0);
        }

        50% {
            transform: translateY(-7px);
        }

        100% {
            transform: translateY(0);
        }
    }

    .auto-map-animation i {
        font-size: 3rem;
        color: var(--tblr-primary);
        display: block;
        margin-bottom: 1rem;
        animation: rotate-icon 2.5s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    }

    @keyframes rotate-icon {
        0% {
            transform: rotate(0deg) scale(1);
            color: var(--tblr-primary);
        }

        25% {
            transform: rotate(90deg) scale(1.1);
            color: #a178ff;
        }

        50% {
            transform: rotate(180deg) scale(1);
            color: #ff78a1;
        }

        75% {
            transform: rotate(270deg) scale(1.1);
            color: #78c0ff;
        }

        100% {
            transform: rotate(360deg) scale(1);
            color: var(--tblr-primary);
        }
    }

    .auto-map-animation span {
        font-size: 1.2rem;
        font-weight: 500;
    }

    /* Stile per il bottone Auto Map */
    #auto-map-btn {
        margin-left: auto;
        box-shadow: 0 2px 5px rgba(var(--tblr-primary-rgb), 0.2);
        transition: all 0.2s ease;
        font-weight: 500;
    }

    #auto-map-btn:hover {
        box-shadow: 0 3px 8px rgba(var(--tblr-primary-rgb), 0.3);
    }

    #auto-map-btn i {
        font-size: 1.1rem;
    }

    /* Effetto stelline che cadono - MIGLIORATO */
    .stars-container {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        overflow: hidden;
        pointer-events: none;
        z-index: 5;
    }

    .star {
        position: absolute;
        width: 10px;
        height: 10px;
        background-size: contain;
        background-repeat: no-repeat;
        animation: falling-star-improved 2s linear infinite;
        opacity: 0;
        filter: drop-shadow(0 0 10px rgba(255, 255, 255, 0.9));
        z-index: 100;
    }

    /* Stelle con diverse dimensioni ma tutti luminose */
    .star:nth-child(3n+1) {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'><path d='M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21 12 17.27z'/></svg>");
        width: 14px;
        height: 14px;
        animation-duration: 1.5s;
    }

    .star:nth-child(3n+2) {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23f8f9fa'><path d='M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21 12 17.27z'/></svg>");
        width: 8px;
        height: 8px;
        filter: drop-shadow(0 0 12px rgba(255, 255, 255, 1));
        animation-duration: 1.2s;
    }

    .star:nth-child(3n) {
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffefcf'><path d='M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21 12 17.27z'/></svg>");
        width: 12px;
        height: 12px;
        filter: drop-shadow(0 0 15px rgba(255, 246, 211, 1));
        animation-duration: 1.8s;
    }

    /* Stelle cadenti con scia luminosa */
    .star:nth-child(5n)::before {
        content: "";
        position: absolute;
        top: 50%;
        left: 50%;
        width: 2px;
        height: 25px;
        background: linear-gradient(to top, transparent, rgba(255, 255, 255, 0.7));
        transform: translate(-50%, -50%) rotate(15deg);
        transform-origin: bottom center;
        z-index: -1;
        opacity: 0.7;
        filter: blur(1.5px);
        border-radius: 50%;
    }

    /* Aggiungiamo stelle scintillanti statiche */
    .star:nth-child(even) {
        animation: falling-star-improved 2s linear infinite, twinkle 1s ease-in-out infinite alternate;
    }

    @keyframes falling-star-improved {
        0% {
            top: -10%;
            transform: translateX(5px) rotate(0deg) scale(1);
            opacity: 0;
        }

        5% {
            opacity: 1;
        }

        80% {
            opacity: 0.9;
        }

        100% {
            top: 110%;
            transform: translateX(-10px) rotate(360deg) scale(0.3);
            opacity: 0;
        }
    }

    /* Stelline minuscole e velocissime */
    .tiny-star {
        position: absolute;
        width: 4px;
        height: 4px;
        background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='%23ffffff'><path d='M12 17.27L18.18 21l-1.64-7.03L22 9.24l-7.19-.62L12 2 9.19 8.62 2 9.24l5.46 4.73L5.82 21 12 17.27z'/></svg>");
        background-size: contain;
        background-repeat: no-repeat;
        animation: tiny-falling-star 1s linear infinite;
        opacity: 0.9;
        filter: drop-shadow(0 0 6px rgba(255, 255, 255, 1));
        z-index: 90;
    }

    /* Posizioni diverse per ogni stella */
    .star:nth-child(1) {
        left: 5%;
        animation-delay: 0s;
    }

    .star:nth-child(2) {
        left: 15%;
        animation-delay: 0.3s;
    }

    .star:nth-child(3) {
        left: 25%;
        animation-delay: 0.6s;
    }

    .star:nth-child(4) {
        left: 35%;
        animation-delay: 0.9s;
    }

    .star:nth-child(5) {
        left: 45%;
        animation-delay: 1.2s;
    }

    .star:nth-child(6) {
        left: 55%;
        animation-delay: 1.5s;
    }

    .star:nth-child(7) {
        left: 65%;
        animation-delay: 1.8s;
    }

    .star:nth-child(8) {
        left: 75%;
        animation-delay: 2.1s;
    }

    .star:nth-child(9) {
        left: 85%;
        animation-delay: 0.4s;
    }

    .star:nth-child(10) {
        left: 95%;
        animation-delay: 0.7s;
    }

    .star:nth-child(11) {
        left: 10%;
        animation-delay: 0.5s;
    }

    .star:nth-child(12) {
        left: 20%;
        animation-delay: 0.2s;
    }

    .star:nth-child(13) {
        left: 30%;
        animation-delay: 0.8s;
    }

    .star:nth-child(14) {
        left: 40%;
        animation-delay: 0.6s;
    }

    .star:nth-child(15) {
        left: 50%;
        animation-delay: 0.9s;
    }

    .star:nth-child(16) {
        left: 60%;
        animation-delay: 0.2s;
    }

    .star:nth-child(17) {
        left: 70%;
        animation-delay: 0.5s;
    }

    .star:nth-child(18) {
        left: 80%;
        animation-delay: 0.8s;
    }

    .star:nth-child(19) {
        left: 90%;
        animation-delay: 0.4s;
    }

    .star:nth-child(20) {
        left: 97%;
        animation-delay: 0.1s;
    }

    /* Posizioni diverse per ogni stellina */
    .tiny-star:nth-child(3n+1) {
        left: calc(10% + var(--random-offset, 0%));
        --random-offset: 2%;
        animation-duration: 0.8s;
    }

    .tiny-star:nth-child(3n+2) {
        left: calc(50% + var(--random-offset, 0%));
        --random-offset: -5%;
        animation-duration: 0.6s;
    }

    .tiny-star:nth-child(3n) {
        left: calc(80% + var(--random-offset, 0%));
        --random-offset: 3%;
        animation-duration: 1.0s;
    }

    /* Alternare i ritardi per le stelline */
    .tiny-star:nth-child(1) {
        animation-delay: 0s;
    }

    .tiny-star:nth-child(2) {
        animation-delay: 0.1s;
    }

    .tiny-star:nth-child(3) {
        animation-delay: 0.2s;
    }

    .tiny-star:nth-child(4) {
        animation-delay: 0.3s;
    }

    .tiny-star:nth-child(5) {
        animation-delay: 0.4s;
    }

    .tiny-star:nth-child(6) {
        animation-delay: 0.5s;
    }

    .tiny-star:nth-child(7) {
        animation-delay: 0.6s;
    }

    .tiny-star:nth-child(8) {
        animation-delay: 0.7s;
    }

    .tiny-star:nth-child(9) {
        animation-delay: 0.15s;
    }

    .tiny-star:nth-child(10) {
        animation-delay: 0.25s;
    }

    .tiny-star:nth-child(11) {
        animation-delay: 0.35s;
    }

    .tiny-star:nth-child(12) {
        animation-delay: 0.45s;
    }

    .tiny-star:nth-child(13) {
        animation-delay: 0.55s;
    }

    .tiny-star:nth-child(14) {
        animation-delay: 0.65s;
    }

    .tiny-star:nth-child(15) {
        animation-delay: 0.75s;
    }

    @keyframes tiny-falling-star {
        0% {
            top: -5%;
            transform: translateX(2px) rotate(0deg);
            opacity: 0.9;
        }

        100% {
            top: 105%;
            transform: translateX(-2px) rotate(180deg);
            opacity: 0;
        }
    }

    /* Effetto polvere magica migliorato - RIMOSSO */
    .auto-map-animation::before {
        content: none;
        animation: none;
    }

    @keyframes magic-dust {

        0%,
        25%,
        50%,
        75%,
        100% {
            box-shadow: none;
            background-image: none;
        }
    }

    @keyframes popInQuestion {
        0% {
            opacity: 0;
        }

        100% {
            opacity: 0;
        }
    }

    @keyframes pulseQuestion {
        0% {
            opacity: 0;
        }

        100% {
            opacity: 0;
        }
    }

    /* Stile per la riga mappatura evidenziata come unique field */
    .mapping-row.unique-field-row {
        background-color: transparent;
        border-left: none;
        transition: none;
    }

    .mapping-row.unique-field-row:hover {
        background-color: rgba(var(--tblr-primary-rgb), 0.03);
    }

    .mapping-row.unique-field-row .table-column {
        color: inherit;
        font-weight: normal;
    }

    /* Evidenzio solo il testo interno con un effetto più compatto */
    .mapping-row .table-column .fw-medium.highlighted-field {
        background-color: rgba(var(--tblr-primary-rgb), 0.12);
        border-radius: 3px;
        font-weight: 500;
        color: var(--tblr-primary);
        padding: 2px 6px 2px 6px;
        display: inline-block;
        position: relative;
        box-shadow: 0 0 5px rgba(var(--tblr-primary-rgb), 0.3);
        transform-origin: center;
        animation: pulse-highlight 2s infinite;
    }

    /* Aggiungo un'icona per il campo unique */
    .mapping-row .table-column .fw-medium.highlighted-field::after {
        content: "\f084";
        /* Codice icona chiave */
        font-family: "Line Awesome Free";
        font-weight: 900;
        position: absolute;
        top: -6px;
        right: -10px;
        background-color: var(--tblr-primary);
        color: white;
        border-radius: 50%;
        font-size: 0.7rem;
        width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.2);
        animation: unique-icon-pulse 2s infinite;
        z-index: 2;
        padding: 2px;
    }

    @keyframes unique-icon-pulse {
        0% {
            transform: scale(1);
        }

        50% {
            transform: scale(1.2);
        }

        100% {
            transform: scale(1);
        }
    }

    @keyframes pulse-highlight {
        0% {
            background-color: rgba(var(--tblr-primary-rgb), 0.12);
            box-shadow: 0 0 5px rgba(var(--tblr-primary-rgb), 0.3);
            transform: scale(1);
        }

        50% {
            background-color: rgba(var(--tblr-primary-rgb), 0.2);
            box-shadow: 0 0 7px rgba(var(--tblr-primary-rgb), 0.4);
            transform: scale(1.03);
        }

        100% {
            background-color: rgba(var(--tblr-primary-rgb), 0.12);
            box-shadow: 0 0 5px rgba(var(--tblr-primary-rgb), 0.3);
            transform: scale(1);
        }
    }

    /* Aggiungi un bordo e un'animazione al campo unique field quando viene selezionato */
    .unique-field-focus {
        animation: focus-pulse 2s 1;
    }

    @keyframes focus-pulse {
        0% {
            box-shadow: 0 0 0 0 rgba(var(--tblr-primary-rgb), 0.7);
        }

        50% {
            box-shadow: 0 0 0 10px rgba(var(--tblr-primary-rgb), 0);
        }

        100% {
            box-shadow: 0 0 0 0 rgba(var(--tblr-primary-rgb), 0);
        }
    }

    /* Fissa l'ordine dei pulsanti nel form */
    .import-form-actions {
        display: flex;
        justify-content: space-between;
        margin-top: 1.5rem;
    }

    .import-cancel-btn {
        order: 1;
        padding: 0.5rem 1.5rem;
    }

    .import-submit-btn {
        order: 2;
        padding: 0.5rem 1.5rem;
        font-weight: 500;
        box-shadow: 0 2px 5px rgba(var(--tblr-primary-rgb), 0.2);
    }

    .import-submit-btn:hover {
        box-shadow: 0 3px 8px rgba(var(--tblr-primary-rgb), 0.3);
        transform: translateY(-1px);
    }
</style>
@endpush

@push('after_scripts')
<script>
    $(document).ready(function() {
        // Gestione dei tooltip per i testi troncati
        const contentTooltip = document.getElementById('contentTooltip');
        let activeTooltipElement = null;

        // Funzione unificata per gestire il click su elementi troncati
        function handleTruncatedClick(e) {
            e.stopPropagation();
            e.preventDefault(); // Previene il comportamento predefinito (selezione testo)

            // Ottieni il testo completo, con fallback sul contenuto stesso
            const fullText = this.getAttribute('data-full-text') || this.textContent.trim();
            if (!fullText || fullText === '') return;

            // Rimuovi la classe active da tutti gli elementi
            document.querySelectorAll('.truncated-text').forEach(el => {
                el.classList.remove('truncated-text-active');
            });

            // Se si clicca sullo stesso elemento, chiudi il tooltip
            if (activeTooltipElement === this) {
                closeTooltip();
                return;
            }

            // Prima fai scorrere l'elemento in vista, se necessario
            const cellElement = this.closest('.csv-cell');
            if (cellElement) {
                // Usa scrollIntoView con behavior smooth per uno scroll naturale
                // e block 'nearest' per evitare di scrollare troppo
                cellElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'nearest'
                });
            }

            // Se c'è già un tooltip attivo, chiudilo prima di aprire il nuovo
            if (activeTooltipElement) {
                // Chiudi il tooltip corrente con animazione
                contentTooltip.classList.remove('show');

                // Memorizza il nuovo elemento da attivare
                const newElement = this;
                const newText = fullText;

                // Attendi la fine dell'animazione di chiusura prima di aprire il nuovo
                setTimeout(() => {
                    // Aggiorna il riferimento all'elemento attivo
                    activeTooltipElement = newElement;

                    // Aggiungi classe active all'elemento corrente
                    newElement.classList.add('truncated-text-active');

                    // Aggiorna il contenuto del tooltip
                    contentTooltip.textContent = newText;

                    // Posiziona il tooltip vicino all'elemento cliccato
                    updateTooltipPosition();

                    // Mostra il tooltip con l'animazione
                    setTimeout(() => {
                        contentTooltip.classList.add('show');
                    }, 10);
                }, 200);

                return;
            }

            // Altrimenti, mostra il tooltip per il nuovo elemento
            activeTooltipElement = this;

            // Aggiungi classe active all'elemento corrente
            this.classList.add('truncated-text-active');

            // Aggiorna il contenuto del tooltip
            contentTooltip.textContent = fullText;

            // Piccolo ritardo per assicurarsi che lo scroll sia completato
            setTimeout(() => {
                // Posiziona il tooltip vicino all'elemento cliccato e poi mostralo
                updateTooltipPosition();

                // Mostra il tooltip con l'animazione
                setTimeout(() => {
                    contentTooltip.classList.add('show');
                }, 10);
            }, 50);
        }

        // Funzione per chiudere il tooltip con animazione
        function closeTooltip() {
            if (!activeTooltipElement) return;

            // Rimuovi la classe active dall'elemento
            activeTooltipElement.classList.remove('truncated-text-active');

            // Nascondi con animazione
            contentTooltip.classList.remove('show');

            // Dopo l'animazione, nascondi completamente
            setTimeout(() => {
                contentTooltip.style.display = 'none';
                activeTooltipElement = null;
            }, 200);
        }

        // Funzione per aggiornare la posizione del tooltip
        function updateTooltipPosition() {
            if (!activeTooltipElement) return;

            const rect = activeTooltipElement.getBoundingClientRect();

            // Calcola la posizione ottimale
            const tooltipWidth = Math.min(400, window.innerWidth - 40);
            contentTooltip.style.maxWidth = tooltipWidth + 'px';

            // Inizializza il posizionamento di base
            let left = rect.left;
            let top = rect.bottom + window.scrollY + 8; // Aggiunto spazio

            // Verifica se il tooltip esce a destra e correggi
            if (left + tooltipWidth > window.innerWidth - 20) {
                left = window.innerWidth - tooltipWidth - 20;
            }

            // Verifica se il tooltip esce a sinistra e correggi
            if (left < 20) {
                left = 20;
            }

            // Verifica se il tooltip esce in basso e correggi posizionandolo sopra l'elemento
            if (top + 100 > window.innerHeight + window.scrollY) { // Stima che il tooltip sia alto ~100px
                top = rect.top + window.scrollY - 100 - 8; // Posiziona sopra con spazio
            }

            // Controllo di sicurezza: se tooltip va fuori schermo in alto, riposiziona sotto
            if (top < window.scrollY) {
                top = rect.bottom + window.scrollY + 8;
            }

            // Imposta la posizione e mostra il tooltip
            contentTooltip.style.left = left + 'px';
            contentTooltip.style.top = top + 'px';
            contentTooltip.style.display = 'block'; // Deve essere visibile prima dell'animazione
        }

        // Funzione per inizializzare i tooltip sugli elementi truncated-text
        function setupTooltips() {
            // Rimuovi eventuali handler esistenti per evitare duplicazioni
            document.querySelectorAll('.truncated-text').forEach(element => {
                element.removeEventListener('click', handleTruncatedClick);
                // Aggiungi il nuovo handler
                element.addEventListener('click', handleTruncatedClick);
            });
        }

        // Chiudi il tooltip quando si clicca altrove
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.truncated-text')) {
                closeTooltip();
            }
        });

        // Aggiorna la posizione del tooltip durante lo scroll
        window.addEventListener('scroll', function() {
            requestAnimationFrame(updateTooltipPosition);
        }, {
            passive: true
        });

        // Aggiorna anche durante lo scroll dei container
        document.querySelectorAll('.csv-preview-container, .table-responsive').forEach(container => {
            container.addEventListener('scroll', function() {
                requestAnimationFrame(updateTooltipPosition);
            }, {
                passive: true
            });
        });

        // Aggiorna anche durante il ridimensionamento della finestra
        window.addEventListener('resize', function() {
            requestAnimationFrame(updateTooltipPosition);
        }, {
            passive: true
        });

        // Combinazione dei due approcci in un'unica funzione robusta
        function makeAllTruncatedElementsClickable() {
            let modificheApportate = false;

            // 1. Prima verifica gli elementi troncati visivamente (CSS overflow)
            // Seleziona solo celle di dati, escludendo esplicitamente le intestazioni di colonna 
            // e altri elementi che non devono triggerare tooltip
            const allCells = document.querySelectorAll('.csv-cell:not(th):not(.table-column)');

            allCells.forEach(element => {
                // Salta elementi che hanno già un figlio con classe truncated-text
                if (element.querySelector('.truncated-text')) return;

                // Salta elementi nella tabella di mapping (non nella preview CSV)
                if (element.closest('.mapping-table')) return;

                try {
                    // Verifica se il testo è troncato visivamente
                    const isOverflowing = element.scrollWidth > element.clientWidth + 1; // Aggiungi tolleranza

                    // Soluzione alternativa se scrollWidth non è affidabile
                    const computedStyle = window.getComputedStyle(element);
                    const hasEllipsis = computedStyle.textOverflow === 'ellipsis';
                    const isFixedWidth = computedStyle.width !== 'auto' && !computedStyle.width.includes('%');
                    const hasOverflow = computedStyle.overflow === 'hidden' ||
                        computedStyle.overflowX === 'hidden' ||
                        computedStyle.whiteSpace === 'nowrap';

                    // Assicurati che ci sia effettivamente del testo nell'elemento e che sia abbastanza lungo
                    const hasSubstantialText = element.textContent.trim().length > 0;

                    // Verifica che il testo abbia una lunghezza sufficiente per POTENZIALMENTE essere troncato
                    const mayBeTruncated = element.textContent.trim().length > 10;

                    // Applica classe truncated solo se effettivamente c'è un troncamento 
                    if (hasSubstantialText && mayBeTruncated &&
                        ((isOverflowing && hasOverflow) || (hasEllipsis && isFixedWidth))) {
                        const originalText = element.textContent.trim();

                        // Crea un nuovo span con il testo completo
                        const truncatedSpan = document.createElement('span');
                        truncatedSpan.className = 'truncated-text';
                        truncatedSpan.setAttribute('data-full-text', originalText);
                        truncatedSpan.textContent = originalText;

                        // Svuota e riempi l'elemento
                        element.textContent = '';
                        element.appendChild(truncatedSpan);
                        modificheApportate = true;
                    }
                } catch (e) {
                    console.error("Errore nel rilevamento testo troncato:", e);
                }
            });

            // 2. Poi trova tutti i testi visibili che contengono "..." ma solo nelle celle di dati
            const textNodes = [];

            function findTextNodes(element) {
                if (element.nodeType === Node.TEXT_NODE) {
                    // Cerca solo nodi che non sono già figli di elementi truncated-text
                    // e che contengono i puntini di sospensione
                    if (element.textContent.includes('...') &&
                        element.parentNode &&
                        !element.parentNode.closest('.truncated-text') &&
                        !element.parentNode.closest('th') &&
                        !element.parentNode.closest('.table-column')) {
                        textNodes.push(element);
                    }
                } else {
                    for (let i = 0; i < element.childNodes.length; i++) {
                        findTextNodes(element.childNodes[i]);
                    }
                }
            }

            // Cerca nei container rilevanti SOLO NELLA PREVIEW CSV
            document.querySelectorAll('.csv-preview-container .csv-cell').forEach(container => {
                if (!container.querySelector('.truncated-text')) {
                    findTextNodes(container);
                }
            });

            // Trasforma i nodi di testo trovati
            textNodes.forEach(textNode => {
                if (textNode.parentNode && !textNode.parentNode.classList.contains('truncated-text')) {
                    // Ottieni tutto il testo prima dei puntini e rimuovi i puntini
                    const displayedText = textNode.textContent.trim();
                    let fullText = '';

                    // Cerca di ottenere il testo completo dal contesto più ampio se possibile
                    const parentElement = textNode.parentNode.closest('.csv-cell');
                    if (parentElement && parentElement.getAttribute('title')) {
                        // Se c'è un attributo title, usalo come testo completo
                        fullText = parentElement.getAttribute('title');
                    } else {
                        // Altrimenti ricostruisci approssimativamente
                        fullText = displayedText;
                        // Se si tratta di un testo troncato, aggiungi un indicatore
                        if (displayedText.endsWith('...')) {
                            fullText += " {{ trans('backpack::import.full_text_unavailable') }}";
                        }
                    }

                    // Crea un nuovo span
                    const span = document.createElement('span');
                    span.className = 'truncated-text';
                    span.setAttribute('data-full-text', fullText);
                    span.textContent = displayedText;

                    // Sostituisci il nodo di testo con lo span
                    textNode.parentNode.replaceChild(span, textNode);
                    modificheApportate = true;
                }
            });

            // In ogni caso, reinizializza i tooltip su tutti gli elementi
            // (sia quelli già esistenti che quelli appena creati)
            setupTooltips();
        }

        // Esegui all'avvio immediatamente per gestire gli elementi generati dal server
        setupTooltips();

        // Poi esegui il rilevamento completo con un breve ritardo per assicurarsi
        // che la pagina sia completamente caricata
        setTimeout(makeAllTruncatedElementsClickable, 300);

        // Esegui altre volte a intervalli crescenti per catturare elementi che potrebbero essere 
        // stati caricati più lentamente o dopo animazioni CSS
        setTimeout(makeAllTruncatedElementsClickable, 1000);
        setTimeout(makeAllTruncatedElementsClickable, 2000);

        // Esegui dopo ogni aggiornamento AJAX
        $(document).ajaxComplete(function() {
            setTimeout(makeAllTruncatedElementsClickable, 300);
        });

        // Aggiungi un listener per mutazioni DOM per catturare elementi aggiunti dinamicamente
        const observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(mutation) {
                if (mutation.addedNodes.length) {
                    setTimeout(setupTooltips, 100);
                }
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true
        });

        // Variabile per tenere traccia del form originale
        const uniqueField = document.getElementById('unique_field');
        const importMappingForm = document.getElementById('import-mapping-form');

        // Impostazione iniziale dei campi select a campi corrispondenti esatti (se esistono)
        function setInitialExactMatches() {
            // Ottieni tutte le righe di mappatura
            const tableColumns = Array.from(document.querySelectorAll('.mapping-row')).map(row => {
                return {
                    element: row,
                    column: row.getAttribute('data-table-column'),
                    select: row.querySelector('.csv-field-select'),
                    indicator: row.querySelector('.mapping-type-indicator')
                };
            });

            // Ottieni tutti gli header CSV
            const csvHeaders = Array.from(document.querySelectorAll('.csv-option')).map(option => {
                return {
                    index: option.value,
                    header: option.getAttribute('data-csv-header')
                };
            });

            // Per ogni colonna della tabella, cerca una corrispondenza esatta
            tableColumns.forEach(tableCol => {
                const exactMatch = csvHeaders.find(csv =>
                    csv.header.toLowerCase() === tableCol.column.toLowerCase());

                // Se c'è una corrispondenza esatta, imposta il valore del select
                if (exactMatch) {
                    tableCol.select.value = exactMatch.index;
                    tableCol.select.classList.add('mapping-match-exact');

                    // Aggiungi anche l'indicatore visivo
                    if (tableCol.indicator) {
                        tableCol.indicator.innerHTML = "<i class='la la-check'></i>{{ trans('backpack::import.exact_match') }}";
                        tableCol.indicator.className = 'mapping-type-indicator mapping-type-exact';
                        tableCol.indicator.classList.add('show');

                        // Nascondi dopo 3 secondi ma mantieni visibile su hover
                        setTimeout(() => {
                            tableCol.indicator.classList.add('hide-after-delay');
                            tableCol.indicator.classList.remove('show');
                        }, 3000);
                    }
                }
            });
        }

        // Esegui la funzione all'avvio della pagina
        setTimeout(setInitialExactMatches, 500);

        // Evidenzia il campo corrispondente quando viene selezionato un valore nel campo unique_field
        if (uniqueField) {
            uniqueField.addEventListener('change', function() {
                // Aggiungi l'effetto di focus al campo unique_field
                this.classList.add('unique-field-focus');
                setTimeout(() => {
                    this.classList.remove('unique-field-focus');
                }, 2000);

                // Rimuovi eventuali evidenziazioni precedenti
                document.querySelectorAll('.fw-medium.highlighted-field').forEach(element => {
                    element.classList.remove('highlighted-field');
                });

                // Ottieni il valore selezionato
                const selectedField = this.value;

                if (selectedField) {
                    // Trova la riga nella tabella che corrisponde al campo selezionato
                    const matchingRow = document.querySelector(`.mapping-row[data-table-column="${selectedField}"]`);
                    if (matchingRow) {
                        // Evidenzia solo il testo del nome colonna
                        const textElement = matchingRow.querySelector('.table-column .fw-medium');
                        if (textElement) {
                            textElement.classList.add('highlighted-field');
                            // Scorri la vista per mostrare il testo evidenziato
                            textElement.scrollIntoView({
                                behavior: 'smooth',
                                block: 'center'
                            });
                        }
                    }
                }
            });
        }

        // Gestisci il campo unique_field che è stato spostato fuori dal form
        if (uniqueField && importMappingForm) {
            // Quando il form viene inviato, intercetta l'evento e gestisci l'invio tramite AJAX
            importMappingForm.addEventListener('submit', function(e) {
                e.preventDefault(); // Previene l'invio standard del form

                // Aggiungi un input hidden per il valore del campo unique_field
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'unique_field';
                hiddenInput.value = uniqueField.value;
                this.appendChild(hiddenInput);

                // Converti la mappatura invertita in quella originale attesa dal backend
                const reverseMapping = {};
                document.querySelectorAll('.csv-field-select').forEach(select => {
                    const tableColumn = select.closest('.mapping-row').getAttribute('data-table-column');
                    const csvIndex = select.value;

                    if (csvIndex) {
                        reverseMapping[csvIndex] = tableColumn;
                    }
                });

                // Aggiungi gli input nascosti per la mappatura originale
                Object.entries(reverseMapping).forEach(([csvIndex, tableColumn]) => {
                    const hiddenMapping = document.createElement('input');
                    hiddenMapping.type = 'hidden';
                    hiddenMapping.name = `column_mapping[${csvIndex}]`;
                    hiddenMapping.value = tableColumn;
                    this.appendChild(hiddenMapping);
                });

                // Raccogli tutti i dati del form
                const formData = new FormData(this);

                // Mostra il pannello di progresso
                $('#import-mapping-form').hide();
                $('#import-progress').show();

                // Invia i dati tramite AJAX
                $.ajax({
                    url: this.action,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    success: function(response) {
                        // Se la risposta è un successo, avvia il monitoraggio dello stato dell'import
                        if (response.success) {
                            monitorImportProgress(response.import_id);
                        } else if (response.status === 'success') {
                            // Se abbiamo una risposta con status "success" ma senza flag "success"
                            // significa che l'importazione è già completata
                            // Nascondi elementi di importazione
                            $('#csvPreviewAccordion').hide();
                            $('.unique-field-card').hide();
                            $('#auto-map-btn').hide(); // Corretto: usa ID invece di classe
                            $('.card-header').hide(); // Nascondi completamente l'intestazione della card
                            $('.import-card').find('h3').hide(); // Nascondi il titolo "Mappatura Colonne"
                            $('.import-card .card-header').hide(); // Nascondi l'intestazione della card
                            $('#import-mapping-form').hide();
                            $('#import-progress').hide();
                            $('#import-results').show();

                            // Aggiorna le statistiche finali con i dati ricevuti
                            $('#result-total-rows').text(response.total || 0);
                            $('#result-created-rows').text(response.created || 0);
                            $('#result-updated-rows').text(response.updated || 0);
                            $('#result-skipped-rows').text(response.skipped || 0);

                            // Aggiorna il nome del file di backup
                            const backupName = response.backupfile || response.backup_filename;
                            $('#backup-filename').text(backupName || '');
                            console.log('Nome backup:', backupName); // Debug

                        } else {
                            // Mostra errore con dettagli completi della risposta
                            showImportError(response.message || "{{ trans('backpack::import.import_error') }}", response);
                        }
                    },
                    error: function(xhr) {
                        // Raccogli informazioni dettagliate sull'errore
                        let errorDetails = {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText
                        };

                        try {
                            // Prova a parsificare la risposta JSON
                            if (xhr.responseText) {
                                errorDetails.parsedResponse = JSON.parse(xhr.responseText);
                            }
                        } catch (e) {
                            // Se non è JSON, usa il testo grezzo
                            errorDetails.parseError = e.message;
                        }

                        // Gestione errori con dettagli
                        showImportError(xhr.responseJSON?.message || "{{ trans('backpack::import.import_error') }}", errorDetails);
                    }
                });
            });
        }

        // Funzione per monitorare lo stato dell'importazione
        function monitorImportProgress(importId) {
            const statusUrl = "{{ url($crud_route.'/import-csv/status') }}";

            // Funzione per aggiornare l'interfaccia con lo stato corrente
            function updateProgressUI(data) {
                // Aggiorna la barra di progresso
                const progressPercentage = Math.round((data.processed / data.total) * 100);
                $('.progress-bar').css('width', progressPercentage + '%');
                $('#progress-text').text(progressPercentage + '%');

                // Aggiorna le statistiche
                $('#total-rows').text(data.processed);
                $('#created-rows').text(data.created || 0);
                $('#updated-rows').text(data.updated || 0);
                $('#skipped-rows').text(data.skipped || 0);

                // Se l'importazione è completata
                if (data.status === 'completed') {
                    // Nascondi elementi di importazione
                    $('#csvPreviewAccordion').hide();
                    $('.unique-field-card').hide();
                    $('#auto-map-btn').hide(); // Corretto: usa ID invece di classe
                    $('.card-header').hide(); // Nascondi completamente l'intestazione della card
                    $('.import-card').find('h3').hide(); // Nascondi il titolo "Mappatura Colonne"
                    $('.import-card .card-header').hide(); // Nascondi l'intestazione della card
                    $('#import-mapping-form').hide();
                    $('#import-progress').hide();
                    $('#import-results').show();

                    // Aggiorna le statistiche finali
                    $('#result-total-rows').text(data.processed || 0);
                    $('#result-created-rows').text(data.created || 0);
                    $('#result-updated-rows').text(data.updated || 0);
                    $('#result-skipped-rows').text(data.skipped || 0);

                    // Aggiorna il nome del file di backup
                    const backupName = data.backup_filename || data.backupfile || '';
                    $('#backup-filename').text(backupName || '');
                    console.log('Nome backup:', backupName); // Debug

                    return true; // Importazione completata
                }

                // Se c'è stato un errore
                if (data.status === 'error') {
                    showImportError(data.message || "{{ trans('backpack::import.import_error') }}", data);
                    return true; // Ferma il polling
                }

                // Se l'importazione è già completata direttamente
                if (data.status === 'success') {
                    // Nascondi elementi di importazione
                    $('#csvPreviewAccordion').hide();
                    $('.unique-field-card').hide();
                    $('#auto-map-btn').hide(); // Corretto: usa ID invece di classe
                    $('.card-header').hide(); // Nascondi completamente l'intestazione della card
                    $('.import-card').find('h3').hide(); // Nascondi il titolo "Mappatura Colonne"
                    $('.import-card .card-header').hide(); // Nascondi l'intestazione della card
                    $('#import-mapping-form').hide();
                    $('#import-progress').hide();
                    $('#import-results').show();

                    // Aggiorna le statistiche finali
                    $('#result-total-rows').text(data.total || 0);
                    $('#result-created-rows').text(data.created || 0);
                    $('#result-updated-rows').text(data.updated || 0);
                    $('#result-skipped-rows').text(data.skipped || 0);

                    // Aggiorna il nome del file di backup
                    const backupName = data.backupfile || data.backup_filename || '';
                    $('#backup-filename').text(backupName || '');
                    console.log('Nome backup:', backupName); // Debug

                    return true; // Importazione completata
                }

                return false; // Continua il polling
            }

            // Funzione ricorsiva per il polling dello stato
            function pollStatus() {
                $.ajax({
                    url: statusUrl,
                    method: 'GET',
                    data: {
                        import_id: importId
                    },
                    dataType: 'json',
                    success: function(data) {
                        const completed = updateProgressUI(data);
                        if (!completed) {
                            // Continua il polling ogni 2 secondi
                            setTimeout(pollStatus, 2000);
                        }
                    },
                    error: function(xhr) {
                        // Raccogli informazioni dettagliate sull'errore di monitoraggio
                        let monitorErrorDetails = {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText,
                            url: statusUrl
                        };

                        // Mostra l'errore con dettagli tecnici
                        showImportError("{{ trans('backpack::import.import_error') }}", monitorErrorDetails);
                    }
                });
            }

            // Avvia il polling
            pollStatus();
        }

        // Funzione per mostrare errori di importazione
        function showImportError(message, details = null) {
            $('#import-progress').hide();
            $('#import-error').show();
            $('#error-message').text(message);

            // Se ci sono dettagli aggiuntivi, mostrarli nell'area dettagli
            if (details) {
                $('#error-details').show();
                let logText = typeof details === 'object' ? JSON.stringify(details, null, 2) : details.toString();
                $('#error-log').text(logText);
            } else {
                $('#error-details').hide();
            }
        }

        // Auto-Map funzionalità
        const autoMapBtn = document.getElementById('auto-map-btn');
        const autoMapOverlay = document.getElementById('auto-map-overlay');

        autoMapBtn.addEventListener('click', function() {
            // Mostra overlay con animazione
            autoMapOverlay.style.display = 'flex';

            // Rimuovi eventuali classi di mapping precedenti
            document.querySelectorAll('.field-mapping-select').forEach(select => {
                select.classList.remove('mapping-match-exact', 'mapping-match-similar', 'mapping-match-none');
            });

            // Raccogli tutti i dati necessari
            const tableColumns = Array.from(document.querySelectorAll('.mapping-row')).map(row => {
                return {
                    element: row,
                    column: row.getAttribute('data-table-column'),
                    select: row.querySelector('.csv-field-select')
                };
            });

            const csvHeaders = Array.from(document.querySelectorAll('.csv-option')).map(option => {
                return {
                    index: option.value,
                    header: option.getAttribute('data-csv-header'),
                    element: option
                };
            });

            // Timeout per simulare l'elaborazione
            setTimeout(() => {
                // Tieni traccia delle colonne CSV già mappate con corrispondenza esatta
                const exactlyMappedCsvIndices = new Set();
                const mappings = [];

                // Primo passaggio: trova tutte le corrispondenze esatte
                tableColumns.forEach(tableCol => {
                    // Passo 1: Cerca corrispondenze esatte (case insensitive)
                    const exactMatches = csvHeaders.filter(csv =>
                        csv.header.toLowerCase() === tableCol.column.toLowerCase());

                    // Se c'è una corrispondenza esatta
                    if (exactMatches.length > 0) {
                        const matchIndex = exactMatches[0].index;
                        tableCol.select.value = matchIndex;
                        mappings.push({
                            element: tableCol.select,
                            type: 'exact'
                        });
                        // Aggiungi questa colonna CSV alle mappate esattamente
                        exactlyMappedCsvIndices.add(matchIndex);
                    }
                });

                // Secondo passaggio: cerca corrispondenze simili solo per le colonne non ancora mappate
                tableColumns.forEach(tableCol => {
                    // Salta se questa colonna della tabella ha già una corrispondenza esatta
                    if (mappings.some(m => m.element === tableCol.select)) {
                        return;
                    }

                    // Passo 2: Cerca corrispondenze con punteggio di similarità
                    const similarityScores = csvHeaders
                        // Filtra le colonne CSV già mappate esattamente, a meno che non siano identiche a questa
                        .filter(csv => !exactlyMappedCsvIndices.has(csv.index) ||
                            csv.header.toLowerCase() === tableCol.column.toLowerCase())
                        .map(csv => {
                            // Normalizza i nomi delle colonne
                            const tableColName = tableCol.column.toLowerCase();
                            const csvHeaderName = csv.header.toLowerCase();

                            // Crea un oggetto per i punteggi dettagliati (per debug)
                            const scoreDetails = {
                                initial: 0,
                                partMatching: 0,
                                prefixMatching: 0,
                                suffixMatching: 0,
                                languageMatching: 0,
                                penalties: 0,
                                total: 0
                            };

                            // Mappa completa delle abbreviazioni linguistiche
                            const languageMap = {
                                'en': 'english',
                                'eng': 'english',
                                'english': 'english',
                                'it': 'italian',
                                'ita': 'italian',
                                'italian': 'italian',
                                'italiano': 'italian',
                                'fr': 'french',
                                'fra': 'french',
                                'french': 'french',
                                'es': 'spanish',
                                'esp': 'spanish',
                                'spa': 'spanish',
                                'spanish': 'spanish',
                                'de': 'german',
                                'deu': 'german',
                                'ger': 'german',
                                'german': 'german'
                            };

                            // Dividi i nomi delle colonne in parti
                            const tableParts = tableColName.split(/[_\s-]|(?=[A-Z])/).filter(p => p.length >= 2);
                            const csvParts = csvHeaderName.split(/[_\s-]|(?=[A-Z])/).filter(p => p.length >= 2);

                            // STEP 1: Verifica se entrambi contengono abbr. linguistiche e se corrispondono
                            let tableLanguage = null;
                            let csvLanguage = null;

                            // Riconosce correttamente i codici lingua solo quando sono isolati o in posizioni chiave
                            const isValidLanguagePart = (part, parts, index) => {
                                // Se è una parte isolata da trattino o underscore, è probabilmente un codice lingua
                                if (parts.length > 1 && (index === 0 || index === parts.length - 1)) {
                                    return true;
                                }

                                // Per abbreviazioni di 2 caratteri, sii più cauto
                                if (part.length <= 2) {
                                    // Controlla se il nome originale contiene "_en" o "en_" o simili
                                    const originalName = index === 0 ? tableColName : csvHeaderName;
                                    return originalName.includes(`_${part}`) ||
                                        originalName.includes(`${part}_`) ||
                                        originalName.endsWith(`_${part}`);
                                }

                                // Per abbreviazioni più lunghe come "eng", "ita", ecc. siamo meno restrittivi
                                return true;
                            };

                            // Controlla l'ultima parte per il suffisso linguistico (più comune)
                            if (tableParts.length > 0 && csvParts.length > 0) {
                                const tableLastIndex = tableParts.length - 1;
                                const csvLastIndex = csvParts.length - 1;
                                const tableLastPart = tableParts[tableLastIndex];
                                const csvLastPart = csvParts[csvLastIndex];

                                if (languageMap[tableLastPart] && isValidLanguagePart(tableLastPart, tableParts, tableLastIndex)) {
                                    tableLanguage = languageMap[tableLastPart];
                                }

                                if (languageMap[csvLastPart] && isValidLanguagePart(csvLastPart, csvParts, csvLastIndex)) {
                                    csvLanguage = languageMap[csvLastPart];
                                }
                            }

                            // Se non trovato in suffisso, cerca ovunque con la validazione
                            if (!tableLanguage) {
                                for (let i = 0; i < tableParts.length; i++) {
                                    const part = tableParts[i];
                                    if (languageMap[part] && isValidLanguagePart(part, tableParts, i)) {
                                        tableLanguage = languageMap[part];
                                        break;
                                    }
                                }
                            }

                            if (!csvLanguage) {
                                for (let i = 0; i < csvParts.length; i++) {
                                    const part = csvParts[i];
                                    if (languageMap[part] && isValidLanguagePart(part, csvParts, i)) {
                                        csvLanguage = languageMap[part];
                                        break;
                                    }
                                }
                            }

                            // Calcola il punteggio linguistico
                            // 1. Entrambe le colonne hanno lingua e corrispondono: punteggio MOLTO alto
                            if (tableLanguage && csvLanguage && tableLanguage === csvLanguage) {
                                scoreDetails.languageMatching = 20; // Punteggio alto per corrispondenza linguistica
                            }
                            // 2. Entrambe le colonne hanno lingua ma NON corrispondono: penalità
                            else if (tableLanguage && csvLanguage && tableLanguage !== csvLanguage) {
                                scoreDetails.penalties -= 30; // Penalità severa se lingue diverse
                            }
                            // 3. Una sola colonna ha lingua: nessun punteggio/penalità

                            // STEP 2: Verifica corrispondenza parti (escluse le parti linguistiche)
                            // Filtra le parti linguistiche in modo più preciso
                            const tableNonLangParts = tableParts.filter((part, index) =>
                                !(languageMap[part] && isValidLanguagePart(part, tableParts, index)));
                            const csvNonLangParts = csvParts.filter((part, index) =>
                                !(languageMap[part] && isValidLanguagePart(part, csvParts, index)));

                            // Se una parte non-linguistica corrisponde esattamente
                            for (const tablePart of tableNonLangParts) {
                                if (csvNonLangParts.includes(tablePart)) {
                                    scoreDetails.partMatching += 5 + Math.min(2, tablePart.length / 2);
                                }

                                // Corrispondenza della parte iniziale di una parola
                                for (const csvPart of csvNonLangParts) {
                                    if (csvPart.startsWith(tablePart) || tablePart.startsWith(csvPart)) {
                                        scoreDetails.partMatching += 3;
                                    }
                                }
                            }

                            // STEP 3: Verifica se i prefissi (parti iniziali) corrispondono
                            if (tableNonLangParts.length > 0 && csvNonLangParts.length > 0) {
                                if (tableNonLangParts[0] === csvNonLangParts[0]) {
                                    scoreDetails.prefixMatching = 10; // Alto boost per corrispondenza prima parte
                                }
                            }

                            // STEP 4: Verifica altri pattern comuni
                            // Se prima e ultima (non-lingua) parte corrispondono ma lunghezza diversa
                            if (tableNonLangParts.length >= 2 && csvNonLangParts.length >= 2) {
                                const tableFirstNonLang = tableNonLangParts[0];
                                const tableLastNonLang = tableNonLangParts[tableNonLangParts.length - 1];
                                const csvFirstNonLang = csvNonLangParts[0];
                                const csvLastNonLang = csvNonLangParts[csvNonLangParts.length - 1];

                                if (tableFirstNonLang === csvFirstNonLang &&
                                    tableLastNonLang === csvLastNonLang) {
                                    scoreDetails.partMatching += 8;
                                }
                            }

                            // Penalizza maggiormente se numero di parti non linguistiche è molto diverso
                            const nonLangPartsDiff = Math.abs(tableNonLangParts.length - csvNonLangParts.length);
                            if (nonLangPartsDiff > 1) {
                                scoreDetails.penalties -= nonLangPartsDiff * 2;
                            }

                            // Calcola il punteggio totale
                            scoreDetails.total = scoreDetails.initial +
                                scoreDetails.partMatching +
                                scoreDetails.prefixMatching +
                                scoreDetails.suffixMatching +
                                scoreDetails.languageMatching +
                                scoreDetails.penalties;

                            return {
                                csv,
                                score: scoreDetails.total
                            };
                        });

                    // Ordina per punteggio di similarità
                    similarityScores.sort((a, b) => b.score - a.score);

                    // Se c'è almeno una corrispondenza con punteggio positivo
                    if (similarityScores.length > 0 && similarityScores[0].score > 0) {
                        tableCol.select.value = similarityScores[0].csv.index;
                        mappings.push({
                            element: tableCol.select,
                            type: 'similar'
                        });
                        return;
                    }

                    // Nessuna corrispondenza trovata
                    mappings.push({
                        element: tableCol.select,
                        type: 'none'
                    });
                });

                // Nascondi l'overlay dopo 2 secondi e poi applica le classi per le animazioni
                setTimeout(() => {
                    autoMapOverlay.style.display = 'none';

                    // Applica le classi per le animazioni dopo che l'overlay è sparito
                    setTimeout(() => {
                        mappings.forEach(mapping => {
                            // Trova l'indicatore associato a questo select
                            const row = mapping.element.closest('.mapping-row');
                            const column = row.getAttribute('data-table-column');
                            const indicator = row.querySelector('.mapping-type-indicator');

                            if (mapping.type === 'exact') {
                                mapping.element.classList.add('mapping-match-exact');
                                if (indicator) {
                                    indicator.innerHTML = "<i class='la la-check'></i> {{ trans('backpack::import.exact_match') }}";
                                    indicator.className = 'mapping-type-indicator mapping-type-exact';
                                    indicator.classList.add('show');

                                    // Nascondi dopo 3 secondi ma mantieni visibile su hover
                                    setTimeout(() => {
                                        indicator.classList.add('hide-after-delay');
                                        indicator.classList.remove('show');
                                    }, 3000);
                                }
                            } else if (mapping.type === 'similar') {
                                mapping.element.classList.add('mapping-match-similar');
                                if (indicator) {
                                    indicator.innerHTML = "<i class='la la-question'></i> {{ trans('backpack::import.similar_match') }}";
                                    indicator.className = 'mapping-type-indicator mapping-type-similar';
                                    indicator.classList.add('show');

                                    // Nascondi dopo 3 secondi ma mantieni visibile su hover
                                    setTimeout(() => {
                                        indicator.classList.add('hide-after-delay');
                                        indicator.classList.remove('show');
                                    }, 3000);
                                }
                            } else {
                                mapping.element.classList.add('mapping-match-none');
                                if (indicator) {
                                    indicator.innerHTML = "<i class='la la-times'></i> {{ trans('backpack::import.no_match') }}";
                                    indicator.className = 'mapping-type-indicator mapping-type-none';
                                    indicator.classList.add('show');

                                    // Nascondi dopo 3 secondi ma mantieni visibile su hover
                                    setTimeout(() => {
                                        indicator.classList.add('hide-after-delay');
                                        indicator.classList.remove('show');
                                    }, 3000);
                                }
                            }
                        });

                        // Reinizializza i tooltip per gli elementi troncati
                        setTimeout(makeAllTruncatedElementsClickable, 100);
                    }, 50); // Un piccolo ritardo per essere sicuri che l'overlay sia sparito
                }, 2000);
            }, 1000);
        });

        // Aggiungi gestione evento change per le select box
        document.querySelectorAll('.csv-field-select').forEach(select => {
            select.addEventListener('change', function() {
                // Rimuovi tutte le classi di stile per la corrispondenza
                this.classList.remove('mapping-match-exact', 'mapping-match-similar', 'mapping-match-none');

                // Nascondi il box di corrispondenza associato e segna come modificato dall'utente
                const row = this.closest('.mapping-row');
                if (row) {
                    const indicator = row.querySelector('.mapping-type-indicator');
                    if (indicator) {
                        indicator.classList.remove('show');
                        indicator.classList.add('hide-after-delay', 'user-modified');
                    }
                }
            });
        });

        // Evidenziazione del campo unique selezionato nella tabella di mapping
        function highlightUniqueFieldInTable() {
            // Rimuovi l'evidenziazione esistente
            document.querySelectorAll('.mapping-row.unique-field-row').forEach(row => {
                row.classList.remove('unique-field-row');
            });

            // Ottieni il valore selezionato nel campo unique_field
            const uniqueFieldSelect = document.getElementById('unique_field');
            if (!uniqueFieldSelect || !uniqueFieldSelect.value) return;

            // Trova la riga corrispondente nella tabella di mapping
            const mappingRow = document.querySelector(`.mapping-row[data-table-column="${uniqueFieldSelect.value}"]`);
            if (mappingRow) {
                // Evidenzia la riga
                mappingRow.classList.add('unique-field-row');

                // Assicurati che la riga sia visibile (opzionale)
                setTimeout(() => {
                    mappingRow.scrollIntoView({
                        behavior: 'smooth',
                        block: 'center'
                    });
                }, 100);
            }
        }

        // Ascolta i cambiamenti nel select unique_field
        const uniqueFieldSelect = document.getElementById('unique_field');
        if (uniqueFieldSelect) {
            uniqueFieldSelect.addEventListener('change', highlightUniqueFieldInTable);

            // Verifica anche all'inizio se c'è già un valore selezionato
            setTimeout(highlightUniqueFieldInTable, 500);
        }

        // ... existing code ...
        setTimeout(function() {
            setInitialExactMatches();
            $(document).on('change', '#unique_field', function() {
                // Aggiungi l'effetto di focus al campo unique_field
                $(this).addClass('unique-field-focus');
                setTimeout(() => {
                    $(this).removeClass('unique-field-focus');
                }, 2000);

                // Rimuovo l'evidenziazione precedente
                $('.fw-medium.highlighted-field').removeClass('highlighted-field');

                // Ottengo il valore selezionato
                let selectedValue = $(this).val();

                if (selectedValue) {
                    // Trovo la riga di mapping corrispondente
                    let targetRow = $(`.mapping-row[data-table-column='${selectedValue}']`);

                    // Evidenzio solo il testo della colonna invece dell'intera cella
                    if (targetRow.length) {
                        targetRow.find('.table-column .fw-medium').addClass('highlighted-field');

                        // Faccio scorrere la visualizzazione sul testo evidenziato
                        $('html, body').animate({
                            scrollTop: targetRow.find('.table-column .fw-medium').offset().top - 200
                        }, 500);
                    }
                }
            });
        }, 500);
        // ... existing code ...

        document.querySelector('#csvPreviewAccordion .accordion-button').addEventListener('click', function() {
            // Piccolo ritardo per assicurarsi che il contenuto sia visibile
            setTimeout(makeAllTruncatedElementsClickable, 300);
        });

        // Riesegui il rilevamento dei testi troncati quando un elemento di tipo collapse viene aperto
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(collapseButton => {
            collapseButton.addEventListener('shown.bs.collapse', function() {
                setTimeout(makeAllTruncatedElementsClickable, 300);
            });
        });
    });
</script>
@endpush