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
        <!-- Matching Fields Selection -->
        <div class="card mb-3 border-primary unique-field-card">
            <div class="card-body">
                <div class="d-flex align-items-center mb-2">
                    <i class="la la-key text-primary fs-3 me-1"></i>
                    <h4 class="mb-0 fw-bold">{{ trans('backpack::import.matching_fields') }}</h4>
                </div>
                <p class="text-muted small mb-3">{{ trans('backpack::import.matching_fields_description') }}</p>

                <div id="matching-fields-container">
                    <div class="matching-field-row d-flex align-items-center gap-2 mb-2">
                        <select name="matching_fields[]" class="form-control form-select matching-field-select">
                            <option value="">{{ trans('backpack::import.select_field') }}</option>
                            @foreach($matchableColumns as $column)
                            <option value="{{ $column }}">{{ $column }}</option>
                            @endforeach
                        </select>
                        <button type="button" class="btn btn-outline-danger btn-sm remove-matching-field" style="display: none;">
                            <i class="la la-times"></i>
                        </button>
                    </div>
                </div>
                
                <button type="button" id="add-matching-field" class="btn btn-outline-primary btn-sm mt-2" style="display: none;">
                    <i class="la la-plus"></i> {{ trans('backpack::import.add_matching_field') }}
                </button>
                
                <!-- Import Behavior Options -->
                <div id="import-behavior-options" class="mt-3 pt-3 border-top" style="display: none;">
                    <p class="mb-2 text-muted"><i class="la la-info-circle"></i> {{ trans('backpack::import.select_import_behavior') }}</p>
                    <div class="import-behavior-radios">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="import_behavior" id="behavior-update-insert" value="update_insert" checked>
                            <label class="form-check-label" for="behavior-update-insert">
                                <i class="la la-plus-circle text-success"></i> {{ trans('backpack::import.update_and_insert') }}
                                <small class="d-block text-muted">{{ trans('backpack::import.update_and_insert_description') }}</small>
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="import_behavior" id="behavior-update-only" value="update_only">
                            <label class="form-check-label" for="behavior-update-only">
                                <i class="la la-sync text-primary"></i> {{ trans('backpack::import.update_only') }}
                                <small class="d-block text-muted">{{ trans('backpack::import.update_only_description') }}</small>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CSV Column Mapping -->
        <div class="card import-card">
            <div class="card-header border-bottom">
                <h3 class="card-title mb-0">
                    <i class="la la-exchange-alt"></i>
                    {{ trans('backpack::import.column_mapping') }}
                </h3>
            </div>
            <div class="card-body">
                <form id="import-mapping-form" class="import-form" method="POST" action="{{ url($crud_route.'/import-csv/process') }}">
                    @csrf
                    <input type="hidden" name="file_path" value="{{ $filePath }}">
                    <input type="hidden" name="delimiter" value="{{ $delimiter }}">
                    @if(isset($originalFileName))
                    <input type="hidden" name="original_file_name" value="{{ $originalFileName }}">
                    @endif
                    @if(isset($originalFileLastModified))
                    <input type="hidden" name="original_file_last_modified" value="{{ $originalFileLastModified }}">
                    @endif

                    <div class="alert alert-info mb-4">
                        <i class="la la-info-circle"></i>
                        {{ trans('backpack::import.mapping_instructions') }}
                    </div>

                    {{-- Matching Fields Mapping Section - rows move here when selected --}}
                    <div id="matching-fields-mapping-section" class="mb-4" style="display: none;">
                        <div class="d-flex align-items-center mb-2">
                            <i class="la la-key text-primary me-2"></i>
                            <h6 class="mb-0 fw-bold text-primary">{{ trans('backpack::import.matching_fields_mapping_title') }}</h6>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm mapping-table matching-fields-mapping-table">
                                <thead class="table-light">
                                    <tr>
                                        <th width="50%">{{ trans('backpack::import.table_field') }}</th>
                                        <th width="50%">{{ trans('backpack::import.csv_column') }}</th>
                                    </tr>
                                </thead>
                                <tbody id="matching-fields-mapping-tbody">
                                    {{-- Matching field rows will be moved here dynamically --}}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table table-sm table-hover mapping-table" id="main-mapping-table">
                            <thead class="table-light">
                                <tr>
                                    <th width="50%">{{ trans('backpack::import.table_field') }}</th>
                                    <th width="50%">{{ trans('backpack::import.csv_column') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($tableColumns as $columnIndex => $column)
                                <tr class="mapping-row" data-table-column="{{ $column }}"
                                    @if(isset($databaseConstraints['primaryKey']) && $databaseConstraints['primaryKey'] === $column) data-is-pk="true" @endif
                                    @if(isset($databaseConstraints['foreignKeys'][$column])) data-is-fk="true" data-fk-table="{{ $databaseConstraints['foreignKeys'][$column] }}" @endif
                                    @if(isset($databaseConstraints['uniqueConstraints'][$column]) && count($databaseConstraints['uniqueConstraints'][$column]) > 0) data-unique-with="{{ implode(',', $databaseConstraints['uniqueConstraints'][$column]) }}" @endif
                                >
                                    <td class="align-middle table-column">
                                        <span class="fw-medium">{{ $column }}</span>
                                        @if(isset($databaseConstraints['primaryKey']) && $databaseConstraints['primaryKey'] === $column)
                                            <span class="badge bg-warning-subtle text-warning-emphasis pk-badge"
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="{{ trans('backpack::import.primary_key_hint') }}"><i class="la la-key me-1"></i>{{ trans('backpack::import.primary_key') }}</span>
                                        @endif
                                        @if(isset($databaseConstraints['foreignKeys'][$column]))
                                            <span class="badge bg-info-subtle text-info-emphasis fk-badge"
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="{{ str_replace(':table', $databaseConstraints['foreignKeys'][$column], trans('backpack::import.foreign_key_hint')) }}"><i class="la la-link me-1"></i>{{ trans('backpack::import.foreign_key') }}</span>
                                        @endif
                                        @if(isset($databaseConstraints['uniqueConstraints'][$column]) && count($databaseConstraints['uniqueConstraints'][$column]) > 0)
                                            <span class="badge bg-purple-subtle text-purple-emphasis unique-constraint-badge"
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="{{ str_replace(':fields', implode(', ', $databaseConstraints['uniqueConstraints'][$column]), trans('backpack::import.unique_constraint_hint')) }}"><i class="la la-fingerprint me-1"></i>{{ trans('backpack::import.unique_constraint') }}: {{ implode(', ', $databaseConstraints['uniqueConstraints'][$column]) }}</span>
                                        @endif
                                        @if(in_array($column, $requiredColumns))
                                            <span class="badge bg-danger-subtle text-danger-emphasis required-field-badge"
                                                  data-bs-toggle="tooltip" 
                                                  data-bs-placement="top" 
                                                  title="{{ trans('backpack::import.required_field_hint') }}">{{ trans('backpack::import.required_field') }}</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center position-relative">
                                            <span class="mapping-type-indicator" data-column="{{ $column }}"></span>
                                            <select name="column_mapping_reverse[{{ $column }}]" class="form-select field-mapping-select csv-field-select @if(in_array($column, $requiredColumns)) required-field-select @endif">
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

                    {{-- Developer Section for auto-generated fields --}}
                    @if(isset($autoGeneratedFields) && count($autoGeneratedFields) > 0)
                    <div class="card mt-4 developer-section-card" id="developer-section">
                        <div class="card-header">
                            <div class="d-flex align-items-center justify-content-between w-100">
                                <div class="d-flex align-items-center">
                                    <i class="la la-code fs-4 me-2"></i>
                                    <h5 class="mb-0 fw-bold">{{ trans('backpack::import.developer_section_title') }}</h5>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="enable-developer-fields" role="switch">
                                    <label class="form-check-label small" for="enable-developer-fields">{{ trans('backpack::import.enable_developer_fields') }}</label>
                                </div>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-secondary mb-3">
                                <i class="la la-info-circle me-1"></i>
                                {{ trans('backpack::import.developer_section_warning') }}
                            </div>
                            
                            <div class="table-responsive">
                                <table class="table table-sm table-hover mapping-table developer-fields-table">
                                    <thead class="table-light">
                                        <tr>
                                            <th width="50%">{{ trans('backpack::import.table_field') }}</th>
                                            <th width="50%">{{ trans('backpack::import.csv_column') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($autoGeneratedFields as $column => $hintKey)
                                        <tr class="mapping-row developer-field-row @if(!empty($isUserModel) && $column === 'password') password-import-row @endif" data-table-column="{{ $column }}"
                                            @if(isset($databaseConstraints['primaryKey']) && $databaseConstraints['primaryKey'] === $column) data-is-pk="true" @endif
                                            @if(isset($databaseConstraints['foreignKeys'][$column])) data-is-fk="true" data-fk-table="{{ $databaseConstraints['foreignKeys'][$column] }}" @endif
                                            @if(isset($databaseConstraints['uniqueConstraints'][$column]) && count($databaseConstraints['uniqueConstraints'][$column]) > 0) data-unique-with="{{ implode(',', $databaseConstraints['uniqueConstraints'][$column]) }}" @endif
                                        >
                                            <td class="align-middle table-column">
                                                <span class="fw-medium">{{ $column }}</span>
                                                @if(isset($databaseConstraints['primaryKey']) && $databaseConstraints['primaryKey'] === $column)
                                                    <span class="badge bg-warning-subtle text-warning-emphasis pk-badge"
                                                          data-bs-toggle="tooltip" 
                                                          data-bs-placement="top" 
                                                          title="{{ trans('backpack::import.primary_key_hint') }}"><i class="la la-key me-1"></i>{{ trans('backpack::import.primary_key') }}</span>
                                                @endif
                                                @if(isset($databaseConstraints['foreignKeys'][$column]))
                                                    <span class="badge bg-info-subtle text-info-emphasis fk-badge"
                                                          data-bs-toggle="tooltip" 
                                                          data-bs-placement="top" 
                                                          title="{{ str_replace(':table', $databaseConstraints['foreignKeys'][$column], trans('backpack::import.foreign_key_hint')) }}"><i class="la la-link me-1"></i>{{ trans('backpack::import.foreign_key') }}</span>
                                                @endif
                                                @if(isset($databaseConstraints['uniqueConstraints'][$column]) && count($databaseConstraints['uniqueConstraints'][$column]) > 0)
                                                    <span class="badge bg-purple-subtle text-purple-emphasis unique-constraint-badge"
                                                          data-bs-toggle="tooltip" 
                                                          data-bs-placement="top" 
                                                          title="{{ str_replace(':fields', implode(', ', $databaseConstraints['uniqueConstraints'][$column]), trans('backpack::import.unique_constraint_hint')) }}"><i class="la la-fingerprint me-1"></i>{{ trans('backpack::import.unique_constraint') }}: {{ implode(', ', $databaseConstraints['uniqueConstraints'][$column]) }}</span>
                                                @endif
                                                @if(!empty($isUserModel) && $column === 'password')
                                                    <span class="password-badge-checkbox-wrap ms-1">
                                                        <span id="password-field-badge" class="badge auto-generated-badge bg-success-subtle text-success-emphasis" data-bs-toggle="tooltip" data-bs-placement="top" data-bs-title="{{ e(trans('backpack::import.' . $hintKey)) }}"
                                                              data-title-auto="{{ e(trans('backpack::import.' . $hintKey)) }}"
                                                              data-title-empty="{{ e(trans('backpack::import.generate_passwords_warning')) }}"
                                                              data-text-auto="{{ e(trans('backpack::import.auto_generated')) }}"
                                                              data-text-empty="{{ e(trans('backpack::import.password_empty_badge')) }}">
                                                            <i class="la la-magic password-badge-icon"></i> <span class="password-badge-text">{{ trans('backpack::import.auto_generated') }}</span>
                                                        </span>
                                                        <span class="form-check form-check-inline mb-0 ms-auto flex-shrink-0">
                                                            <input class="form-check-input" type="checkbox" name="generate_passwords" id="generate_passwords" value="1" checked>
                                                            <label class="form-check-label small mb-0" for="generate_passwords">{{ trans('backpack::import.generate_passwords_label') }}</label>
                                                        </span>
                                                    </span>
                                                @else
                                                <span class="badge ms-1 auto-generated-badge" 
                                                      data-bs-toggle="tooltip" 
                                                      data-bs-placement="top" 
                                                      title="{{ trans('backpack::import.' . $hintKey) }}">
                                                    <i class="la la-magic"></i> {{ trans('backpack::import.auto_generated') }}
                                                </span>
                                                @endif
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center position-relative gap-2 flex-wrap">
                                                    <span class="mapping-type-indicator" data-column="{{ $column }}"></span>
                                                    <select name="column_mapping_reverse[{{ $column }}]" class="form-select field-mapping-select csv-field-select developer-field-select" disabled>
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
                        </div>
                    </div>
                    @endif

                    <div class="form-group mt-4 import-form-actions">
                        <a href="{{ url($crud_route) }}" class="btn btn-outline-secondary import-cancel-btn">
                            <i class="la la-ban"></i>
                            {{ trans('backpack::import.cancel') }}
                        </a>
                        <button type="button" class="btn btn-success import-submit-btn" id="start-import">
                            <i class="la la-eye"></i>
                            Mostra anteprima
                        </button>
                    </div>
                </form>

                <div id="import-progress" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3 import-status-header">
                            <h4 class="text-primary mb-0 d-flex align-items-center gap-2">
                                <i class="la la-sync fa-spin"></i>
                            {{ trans('backpack::import.import_in_progress') }}
                        </h4>
                            <div class="d-flex align-items-center gap-2">
                                <div class="import-loader-wrapper">
                                    <div class="import-loader-waves">
                                        <div class="wave"></div>
                                        <div class="wave"></div>
                                        <div class="wave"></div>
                                    </div>
                                </div>
                                <span id="progress-text" class="badge text-white bg-primary fs-6">0%</span>
                            </div>
                        </div>

                        <div class="mt-3">
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
                        
                        <!-- Log operazioni in tempo reale -->
                        <div class="mt-4">
                            <h5 class="text-primary mb-3">
                                <i class="la la-terminal me-2"></i>
                                {{ trans('backpack::import.operation_log_title') }}
                            </h5>
                            
                            <!-- Box operazione corrente -->
                            <div id="current-operation-box" class="border rounded bg-light p-3 mb-3">
                                <div class="d-flex align-items-center">
                                    <div class="flex-shrink-0">
                                        <i id="current-op-icon" class="la la-info-circle fs-2 text-primary"></i>
                                    </div>
                                    <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1 text-dark">
                                            <span id="current-op-title">{{ trans('backpack::import.import_in_progress') }}</span>
                                        </h6>
                                        <p id="current-op-details" class="mb-0 text-muted">
                                            <span id="operation-text">{{ trans('backpack::import.import_in_progress') }}</span>
                                        </p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Log storico compatto -->
                            <div class="border rounded bg-white p-2 import-log-container">
                                <div id="operation-log" class="small">
                                    <div class="text-muted">{{ trans('backpack::import.import_in_progress') }}</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="import-results" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3 import-status-header">
                            <h4 class="text-success mb-0 d-flex align-items-center gap-2">
                                <i class="la la-check-circle"></i>
                            {{ trans('backpack::import.import_completed') }}
                        </h4>
                            <span id="result-progress-text" class="badge text-white bg-success fs-6">100%</span>
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
                                <code>/storage/backups/csv-imports/</code>
                            </span>
                        </div>
                        
                        <div class="alert alert-success mt-3" id="log-path-alert" style="display: none;">
                            <i class="la la-file-alt me-2"></i>
                            <span>
                                <strong>Log import salvati in:</strong>
                                <code id="log-path-text">-</code>
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
                        <h4 class="alert-heading mb-3">
                            <i class="la la-exclamation-circle me-2"></i>
                            {{ trans('backpack::import.import_error') }}
                        </h4>
                        <div id="error-details" class="border rounded bg-light p-3 mb-4" style="display: none;">
                            <h6 class="text-danger mb-3"><i class="la la-bug"></i> Technical Error Details:</h6>
                            <pre id="error-log" class="small text-pre-wrap bg-dark text-light p-3 rounded" style="max-height: 400px; overflow-y: auto; overflow-x: auto; word-wrap: break-word; white-space: pre-wrap;"></pre>
                        </div>
                        <div class="pt-3 border-top">
                            <p class="mb-2">
                                <i class="la la-info-circle me-2"></i>
                                {{ trans('backpack::import.backup_created') }}
                                <code>/storage/backups/csv-imports/</code>
                            </p>
                            <div id="error-log-path" class="mt-2" style="display: none;">
                                <i class="la la-file-alt me-2"></i>
                                <strong>Log import salvati in:</strong>
                                <code id="error-log-path-text">-</code>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Preview Results Section -->
                <div id="import-preview-results" style="display: none;">
                    <div class="progress-container p-4 border rounded bg-light">
                        <div class="d-flex align-items-center gap-3 flex-wrap mb-3 import-status-header">
                            <h4 class="text-info mb-0 d-flex align-items-center gap-2">
                                <i class="la la-eye"></i>
                                Anteprima import
                            </h4>
                            <span id="preview-status-badge" class="badge text-white bg-info fs-6">Analisi...</span>
                        </div>

                        <!-- Preview Summary Stats -->
                        <div id="preview-stats" class="stats-container mt-3">
                            <div class="row">
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">Righe totali</div>
                                            <div class="h3" id="preview-total-rows">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">Nuovi record</div>
                                            <div class="h3 text-success" id="preview-will-insert">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">Record aggiornati</div>
                                            <div class="h3 text-primary" id="preview-will-update">0</div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-sm-6 col-md-3 mb-2">
                                    <div class="card border-0 shadow-sm h-100">
                                        <div class="card-body text-center">
                                            <div class="h5 text-muted mb-2">Record saltati</div>
                                            <div class="h3 text-warning" id="preview-will-skip">0</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Preview Rows Table (all rows, problems first) -->
                        <div id="preview-rows-section" class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5 class="mb-0">
                                    <i class="la la-list me-2"></i>
                                    Anteprima righe (<span id="preview-rows-shown-count">0</span> di <span id="preview-rows-total-count">0</span>)
                                </h5>
                                <div class="d-flex align-items-center gap-2">
                                    <label class="form-label mb-0 small text-muted">Righe per pagina:</label>
                                    <select id="preview-page-size" class="form-select form-select-sm" style="width: auto;">
                                        <option value="50">50</option>
                                        <option value="100" selected>100</option>
                                        <option value="200">200</option>
                                        <option value="500">500</option>
                                    </select>
                                </div>
                            </div>
                            <div style="max-height: 500px; overflow-y: auto; overflow-x: auto; border: 1px solid #dee2e6;">
                                <table class="table table-sm table-hover table-striped mb-0" id="preview-rows-table">
                                    <thead class="table-light" id="preview-rows-thead" style="position: sticky; top: 0; z-index: 1; background-color: #f8f9fa;">
                                    </thead>
                                    <tbody id="preview-rows-tbody">
                                    </tbody>
                                </table>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2" id="preview-pagination" style="display: none;">
                                <span class="small text-muted" id="preview-page-info"></span>
                                <div class="btn-group btn-group-sm">
                                    <button type="button" class="btn btn-outline-secondary" id="preview-prev-page" disabled>
                                        <i class="la la-angle-left"></i>
                                    </button>
                                    <span class="btn btn-light" id="preview-page-num">1</span>
                                    <button type="button" class="btn btn-outline-secondary" id="preview-next-page">
                                        <i class="la la-angle-right"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between mt-4">
                            <button type="button" class="btn btn-outline-secondary" id="preview-close-btn">
                                <i class="la la-arrow-left"></i> Torna al mapping
                            </button>
                            <button type="submit" class="btn btn-success" id="preview-confirm-btn" form="import-mapping-form">
                                <i class="la la-play"></i> Avvia import
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Tooltip container to show full text -->
<div class="content-tooltip" id="contentTooltip"></div>

@endsection

@push('after_scripts')

<script>
    $(document).ready(function() {
        // Set per tenere traccia degli ID di operazione già visualizzati
        const processedOperationIds = new Set();
        
        // Handling tooltips for truncated texts
        const contentTooltip = document.getElementById('contentTooltip');
        let activeTooltipElement = null;

        // Unified function to handle clicks on truncated elements
        function handleTruncatedClick(e) {
            e.stopPropagation();
            e.preventDefault(); // Prevents default behavior (text selection)

            // Get the full text, with fallback to the content itself
            const fullText = this.getAttribute('data-full-text') || this.textContent.trim();
            if (!fullText || fullText === '') return;

            // Remove active class from all elements
            document.querySelectorAll('.truncated-text').forEach(el => {
                el.classList.remove('truncated-text-active');
            });

            // If clicking on the same element, close the tooltip
            if (activeTooltipElement === this) {
                closeTooltip();
                return;
            }

            // First scroll the element into view, if necessary
            const cellElement = this.closest('.csv-cell');
            if (cellElement) {
                // Use scrollIntoView with smooth behavior for natural scrolling
                // and 'nearest' block to avoid scrolling too much
                cellElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'nearest',
                    inline: 'nearest'
                });
            }

            // If there's already an active tooltip, close it before opening the new one
            if (activeTooltipElement) {
                // Close the current tooltip with animation
                contentTooltip.classList.remove('show');

                // Store the new element to activate
                const newElement = this;
                const newText = fullText;

                // Wait for the closing animation to finish before opening the new one
                setTimeout(() => {
                    // Update the reference to the active element
                    activeTooltipElement = newElement;

                    // Add active class to the current element
                    newElement.classList.add('truncated-text-active');

                    // Update tooltip content
                    contentTooltip.textContent = newText;

                    // Position the tooltip near the clicked element
                    updateTooltipPosition();

                    // Show the tooltip with animation
                    setTimeout(() => {
                        contentTooltip.classList.add('show');
                    }, 10);
                }, 200);

                return;
            }

            // Otherwise, show the tooltip for the new element
            activeTooltipElement = this;

            // Add active class to the current element
            this.classList.add('truncated-text-active');

            // Update tooltip content
            contentTooltip.textContent = fullText;

            // Small delay to ensure scrolling is complete
            setTimeout(() => {
                // Position the tooltip near the clicked element and then show it
                updateTooltipPosition();

                // Show the tooltip with animation
                setTimeout(() => {
                    contentTooltip.classList.add('show');
                }, 10);
            }, 50);
        }

        // Function to close the tooltip with animation
        function closeTooltip() {
            if (!activeTooltipElement) return;

            // Remove the active class from the element
            activeTooltipElement.classList.remove('truncated-text-active');

            // Hide with animation
            contentTooltip.classList.remove('show');

            // After animation, hide completely
            setTimeout(() => {
                contentTooltip.style.display = 'none';
                activeTooltipElement = null;
            }, 200);
        }

        // Function to update tooltip position
        function updateTooltipPosition() {
            if (!activeTooltipElement) return;

            const rect = activeTooltipElement.getBoundingClientRect();

            // Calculate optimal position
            const tooltipWidth = Math.min(400, window.innerWidth - 40);
            contentTooltip.style.maxWidth = tooltipWidth + 'px';

            // Initialize base positioning
            let left = rect.left;
            let top = rect.bottom + window.scrollY + 8; // Added space

            // Check if tooltip goes off the right edge and adjust
            if (left + tooltipWidth > window.innerWidth - 20) {
                left = window.innerWidth - tooltipWidth - 20;
            }

            // Check if tooltip goes off the left edge and adjust
            if (left < 20) {
                left = 20;
            }

            // Check if tooltip goes off the bottom and adjust by positioning above the element
            if (top + 100 > window.innerHeight + window.scrollY) { // Estimate tooltip height ~100px
                top = rect.top + window.scrollY - 100 - 8; // Position above with space
            }

            // Safety check: if tooltip goes off screen at the top, reposition below
            if (top < window.scrollY) {
                top = rect.bottom + window.scrollY + 8;
            }

            // Set position and show tooltip
            contentTooltip.style.left = left + 'px';
            contentTooltip.style.top = top + 'px';
            contentTooltip.style.display = 'block'; // Must be visible before animation
        }

        // Function to initialize tooltips on truncated-text elements
        function setupTooltips() {
            // Remove any existing handlers to avoid duplications
            document.querySelectorAll('.truncated-text').forEach(element => {
                element.removeEventListener('click', handleTruncatedClick);
                // Add the new handler
                element.addEventListener('click', handleTruncatedClick);
            });
        }

        // Close the tooltip when clicking elsewhere
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.truncated-text')) {
                closeTooltip();
            }
        });

        // Update tooltip position during scrolling
        window.addEventListener('scroll', function() {
            requestAnimationFrame(updateTooltipPosition);
        }, {
            passive: true
        });

        // Also update during container scrolling
        document.querySelectorAll('.table-responsive').forEach(container => {
            container.addEventListener('scroll', function() {
                requestAnimationFrame(updateTooltipPosition);
            }, {
                passive: true
            });
        });

        // Also update during window resizing
        window.addEventListener('resize', function() {
            requestAnimationFrame(updateTooltipPosition);
        }, {
            passive: true
        });

        // Combination of two approaches in a single robust function
        function makeAllTruncatedElementsClickable() {
            let changesApplied = false;

            // 1. First check visually truncated elements (CSS overflow)
            // Select only data cells, explicitly excluding column headers
            // and other elements that should not trigger tooltips
            const allCells = document.querySelectorAll('.csv-cell:not(th):not(.table-column)');

            allCells.forEach(element => {
                // Skip elements that already have a child with truncated-text class
                if (element.querySelector('.truncated-text')) return;

                // Skip elements in the mapping table
                if (element.closest('.mapping-table')) return;

                try {
                    // Check if the text is visually truncated
                    const isOverflowing = element.scrollWidth > element.clientWidth + 1; // Add tolerance

                    // Alternative solution if scrollWidth is not reliable
                    const computedStyle = window.getComputedStyle(element);
                    const hasEllipsis = computedStyle.textOverflow === 'ellipsis';
                    const isFixedWidth = computedStyle.width !== 'auto' && !computedStyle.width.includes('%');
                    const hasOverflow = computedStyle.overflow === 'hidden' ||
                        computedStyle.overflowX === 'hidden' ||
                        computedStyle.whiteSpace === 'nowrap';

                    // Make sure there's actually text in the element and it's long enough
                    const hasSubstantialText = element.textContent.trim().length > 0;

                    // Check that the text is long enough to POTENTIALLY be truncated
                    const mayBeTruncated = element.textContent.trim().length > 10;

                    // Apply truncated class only if there's actually truncation
                    if (hasSubstantialText && mayBeTruncated &&
                        ((isOverflowing && hasOverflow) || (hasEllipsis && isFixedWidth))) {
                        const originalText = element.textContent.trim();

                        // Create a new span with the full text
                        const truncatedSpan = document.createElement('span');
                        truncatedSpan.className = 'truncated-text';
                        truncatedSpan.setAttribute('data-full-text', originalText);
                        truncatedSpan.textContent = originalText;

                        // Empty and refill the element
                        element.textContent = '';
                        element.appendChild(truncatedSpan);
                        changesApplied = true;
                    }
                } catch (e) {
                    console.error("Error detecting truncated text:", e);
                }
            });

            // 2. Then find all visible texts that contain "..." but only in data cells
            const textNodes = [];

            function findTextNodes(element) {
                if (element.nodeType === Node.TEXT_NODE) {
                    // Look only for nodes that aren't already children of truncated-text elements
                    // and that contain ellipses
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


            // Transform the found text nodes
            textNodes.forEach(textNode => {
                if (textNode.parentNode && !textNode.parentNode.classList.contains('truncated-text')) {
                    // Get all text before the ellipses and remove the ellipses
                    const displayedText = textNode.textContent.trim();
                    let fullText = '';

                    // Try to get the full text from the broader context if possible
                    const parentElement = textNode.parentNode.closest('.csv-cell');
                    if (parentElement && parentElement.getAttribute('title')) {
                        // If there's a title attribute, use it as the full text
                        fullText = parentElement.getAttribute('title');
                    } else {
                        // Otherwise reconstruct approximately
                        fullText = displayedText;
                        // If it's truncated text, add an indicator
                        if (displayedText.endsWith('...')) {
                            fullText += " {{ trans('backpack::import.full_text_unavailable') }}";
                        }
                    }

                    // Create a new span
                    const span = document.createElement('span');
                    span.className = 'truncated-text';
                    span.setAttribute('data-full-text', fullText);
                    span.textContent = displayedText;

                    // Replace the text node with the span
                    textNode.parentNode.replaceChild(span, textNode);
                    changesApplied = true;
                }
            });

            // In any case, reinitialize tooltips on all elements
            // (both existing ones and newly created ones)
            setupTooltips();
        }

        // Run immediately at startup to handle server-generated elements
        setupTooltips();

        // Then run the complete detection with a short delay to ensure
        // the page is fully loaded
        setTimeout(makeAllTruncatedElementsClickable, 300);

        // Run more times at increasing intervals to catch elements that might have been
        // loaded more slowly or after CSS animations
        setTimeout(makeAllTruncatedElementsClickable, 1000);
        setTimeout(makeAllTruncatedElementsClickable, 2000);

        // Run after every AJAX update
        $(document).ajaxComplete(function() {
            setTimeout(makeAllTruncatedElementsClickable, 300);
        });

        // Add a listener for DOM mutations to capture dynamically added elements
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

        // Variable to track the original form
        const importMappingForm = document.getElementById('import-mapping-form');
        const matchingFieldsContainer = document.getElementById('matching-fields-container');
        const addMatchingFieldBtn = document.getElementById('add-matching-field');
        const behaviorOptions = document.getElementById('import-behavior-options');
        
        // Template for new matching field row
        const matchingFieldTemplate = `
            <div class="matching-field-row d-flex align-items-center gap-2 mb-2">
                <select name="matching_fields[]" class="form-control form-select matching-field-select">
                    <option value="">{{ trans('backpack::import.select_field') }}</option>
                    @foreach($matchableColumns as $column)
                    <option value="{{ $column }}">{{ $column }}</option>
                    @endforeach
                </select>
                <button type="button" class="btn btn-outline-danger btn-sm remove-matching-field">
                    <i class="la la-times"></i>
                </button>
            </div>
        `;
        
        // Get all selected matching fields
        function getSelectedMatchingFields() {
            const fields = [];
            document.querySelectorAll('.matching-field-select').forEach(select => {
                if (select.value) {
                    fields.push(select.value);
                }
            });
            return fields;
        }

        // Get all mapped table columns (selected in the mapping table)
        function getMappedTableColumns() {
            const mapped = [];
            // Check main mapping table - get table column from row's data attribute when select has value
            document.querySelectorAll('.mapping-row:not(.developer-field-row)').forEach(row => {
                const select = row.querySelector('.csv-field-select');
                if (select && select.value) {
                    const tableColumn = row.getAttribute('data-table-column');
                    if (tableColumn) {
                        mapped.push(tableColumn);
                    }
                }
            });
            // Also check developer section selects
            // Note: for matching fields, we check value even if disabled (matching fields force-enable their selects)
            document.querySelectorAll('.mapping-row.developer-field-row').forEach(row => {
                const select = row.querySelector('.developer-field-select');
                const isMatchingRequired = row.classList.contains('matching-field-required');
                // Include if: has value AND (is matching field OR not disabled)
                if (select && select.value && (isMatchingRequired || !select.disabled)) {
                    const tableColumn = row.getAttribute('data-table-column');
                    if (tableColumn) {
                        mapped.push(tableColumn);
                    }
                }
            });
            return mapped;
        }

        // Check if all matching fields are mapped and return unmapped ones
        function getUnmappedMatchingFields() {
            const matchingFields = getSelectedMatchingFields();
            const mappedColumns = getMappedTableColumns();
            return matchingFields.filter(field => !mappedColumns.includes(field));
        }

        // Update UI based on selected matching fields
        function updateMatchingFieldsUI() {
            const selectedFields = getSelectedMatchingFields();
            const rows = document.querySelectorAll('.matching-field-row');
            
            // Show/hide add button based on whether first field is selected
            if (selectedFields.length > 0) {
                addMatchingFieldBtn.style.display = 'inline-block';
                behaviorOptions.style.display = 'block';
                behaviorOptions.style.opacity = '1';
            } else {
                addMatchingFieldBtn.style.display = 'none';
                behaviorOptions.style.display = 'none';
            }
            
            // Show/hide remove buttons (hide if only one row)
            rows.forEach((row, index) => {
                const removeBtn = row.querySelector('.remove-matching-field');
                if (rows.length > 1) {
                    removeBtn.style.display = 'inline-block';
                } else {
                    removeBtn.style.display = 'none';
                }
            });
            
            // Update highlights in mapping table
            highlightMatchingFields(selectedFields);
            
            // Disable already selected options in other selects
            updateSelectOptions();
        }
        
        // Disable already selected options in other selects
        function updateSelectOptions() {
            const allSelects = document.querySelectorAll('.matching-field-select');
            const selectedValues = [];
            
            allSelects.forEach(select => {
                if (select.value) {
                    selectedValues.push(select.value);
                }
            });
            
            allSelects.forEach(select => {
                const currentValue = select.value;
                select.querySelectorAll('option').forEach(option => {
                    if (option.value && option.value !== currentValue && selectedValues.includes(option.value)) {
                        option.disabled = true;
                    } else {
                        option.disabled = false;
                    }
                });
            });
        }
        
        // Store original positions for rows
        const originalRowPositions = new Map();
        
        // Initialize original positions on page load
        document.querySelectorAll('.mapping-row').forEach(row => {
            const column = row.getAttribute('data-table-column');
            const isDeveloper = row.classList.contains('developer-field-row');
            const parent = row.parentElement;
            const siblings = Array.from(parent.children);
            originalRowPositions.set(column, {
                parent: parent,
                index: siblings.indexOf(row),
                isDeveloper: isDeveloper
            });
        });
        
        // Move matching fields to top section or back to original position
        function highlightMatchingFields(fields) {
            const matchingSection = document.getElementById('matching-fields-mapping-section');
            const matchingTbody = document.getElementById('matching-fields-mapping-tbody');
            const mainTableTbody = document.querySelector('#main-mapping-table tbody');
            const developerTableTbody = document.querySelector('.developer-fields-table tbody');
            
            // First, move ALL rows back to their original positions
            document.querySelectorAll('.mapping-row.matching-field-required').forEach(row => {
                const column = row.getAttribute('data-table-column');
                const originalPos = originalRowPositions.get(column);
                
                // Remove matching classes and badge
                row.classList.remove('unique-field-selected');
                row.classList.remove('matching-field-required');
                const existingBadge = row.querySelector('.matching-field-badge');
                if (existingBadge) {
                    // Dispose tooltip before removing badge
                    const tooltip = bootstrap.Tooltip.getInstance(existingBadge);
                    if (tooltip) {
                        tooltip.dispose();
                    }
                    existingBadge.remove();
                }
                
                // Show the required badge again if it was hidden
                const requiredBadge = row.querySelector('.required-field-badge');
                if (requiredBadge) {
                    requiredBadge.style.display = '';
                }
                
                // Re-enable "do not import" option
                const select = row.querySelector('.csv-field-select, .developer-field-select');
                if (select) {
                    const emptyOption = select.querySelector('option[value=""]');
                    if (emptyOption) {
                        emptyOption.disabled = false;
                    }
                    select.classList.remove('is-invalid');
                    
                    // If it's a developer field, disable it again (unless developer section is enabled)
                    if (originalPos && originalPos.isDeveloper) {
                        const developerToggle = document.getElementById('enable-developer-fields');
                        if (developerToggle && !developerToggle.checked) {
                            select.disabled = true;
                        }
                    }
                }
                
                // Move back to original position
                if (originalPos) {
                    const targetParent = originalPos.isDeveloper ? developerTableTbody : mainTableTbody;
                    if (targetParent && row.parentElement !== targetParent) {
                        // Insert at original index or at end
                        const siblings = Array.from(targetParent.children);
                        if (originalPos.index < siblings.length) {
                            targetParent.insertBefore(row, siblings[originalPos.index]);
                        } else {
                            targetParent.appendChild(row);
                        }
                    }
                }
            });
            
            // Now move selected fields to matching section
            if (fields.length > 0) {
                matchingSection.style.display = 'block';
                
                fields.forEach(fieldName => {
                    const selectedRow = document.querySelector(`.mapping-row[data-table-column="${fieldName}"]`);
                    if (selectedRow) {
                        selectedRow.classList.add('unique-field-selected');
                        selectedRow.classList.add('matching-field-required');
                        
                        // Hide the required badge (matching badge replaces it)
                        const requiredBadge = selectedRow.querySelector('.required-field-badge');
                        if (requiredBadge) {
                            requiredBadge.style.display = 'none';
                        }
                        
                        // Add matching badge next to field name (red, required style)
                        const tableColumn = selectedRow.querySelector('.table-column');
                        if (tableColumn && !tableColumn.querySelector('.matching-field-badge')) {
                            const badge = document.createElement('span');
                            badge.className = 'badge ms-2 matching-field-badge matching-required-badge';
                            badge.setAttribute('data-bs-toggle', 'tooltip');
                            badge.setAttribute('data-bs-placement', 'top');
                            badge.setAttribute('title', "{{ trans('backpack::import.matching_field_hint') }}");
                            badge.innerHTML = '<i class="la la-key me-1"></i>{{ trans("backpack::import.matching_required") }}';
                            tableColumn.appendChild(badge);
                            // Initialize tooltip
                            new bootstrap.Tooltip(badge);
                        }
                        
                        // Enable and make required the select
                        const select = selectedRow.querySelector('.csv-field-select, .developer-field-select');
                        if (select) {
                            select.disabled = false; // Always enable for matching fields
                            const emptyOption = select.querySelector('option[value=""]');
                            if (emptyOption) {
                                emptyOption.disabled = true;
                            }
                            if (!select.value) {
                                select.classList.add('is-invalid');
                            }
                        }
                        
                        // Move row to matching section
                        matchingTbody.appendChild(selectedRow);
                    }
                });
            } else {
                matchingSection.style.display = 'none';
            }
            
            // Update import behavior UI (for unique constraint warnings)
            handleImportBehaviorChange();
        }
        
        // Handle change on matching field selects
        function handleMatchingFieldChange(e) {
            updateMatchingFieldsUI();
        }
        
        // Add event listeners to existing selects
        document.querySelectorAll('.matching-field-select').forEach(select => {
            select.addEventListener('change', handleMatchingFieldChange);
        });

        // Handle mapping select change - update validation state
        function handleMappingSelectChange(e) {
            const select = e.target;
            const row = select.closest('.mapping-row');
            
            // Remove invalid state if value is selected
            if (select.value && row && row.classList.contains('matching-field-required')) {
                select.classList.remove('is-invalid');
            }
        }

        // Add event listeners to mapping table selects
        document.querySelectorAll('.csv-field-select').forEach(select => {
            select.addEventListener('change', handleMappingSelectChange);
        });

        // Add event listeners to developer section selects
        document.querySelectorAll('.developer-field-select').forEach(select => {
            select.addEventListener('change', handleMappingSelectChange);
        });
        
        // Add new matching field row
        addMatchingFieldBtn.addEventListener('click', function() {
            const newRow = document.createElement('div');
            newRow.innerHTML = matchingFieldTemplate;
            const rowElement = newRow.firstElementChild;
            matchingFieldsContainer.appendChild(rowElement);
            
            // Add event listeners to new elements
            rowElement.querySelector('.matching-field-select').addEventListener('change', handleMatchingFieldChange);
            rowElement.querySelector('.remove-matching-field').addEventListener('click', function() {
                rowElement.remove();
                updateMatchingFieldsUI();
            });
            
            updateMatchingFieldsUI();
        });
        
        // Handle remove button on first row (delegated)
        document.querySelectorAll('.remove-matching-field').forEach(btn => {
            btn.addEventListener('click', function() {
                const row = this.closest('.matching-field-row');
                const rows = document.querySelectorAll('.matching-field-row');
                if (rows.length > 1) {
                    row.remove();
                    updateMatchingFieldsUI();
                }
            });
        });

        // Handle import behavior change (update-only vs upsert)
        function handleImportBehaviorChange() {
            const selectedMatchingFields = getSelectedMatchingFields();
            const hasMatchingFields = selectedMatchingFields.length > 0;
            
            // If no matching fields selected, treat as normal insert mode (not update-only)
            const isUpdateOnly = hasMatchingFields && document.getElementById('behavior-update-only').checked;
            
            const requiredBadges = document.querySelectorAll('#main-mapping-table .required-field-badge');
            const requiredSelects = document.querySelectorAll('#main-mapping-table .required-field-select');
            
            requiredBadges.forEach(badge => {
                // Dispose existing tooltip
                const existingTooltip = bootstrap.Tooltip.getInstance(badge);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                
                if (isUpdateOnly) {
                    // Opacify badge and change tooltip for update-only mode
                    badge.classList.add('required-field-badge-relaxed');
                    badge.setAttribute('title', "{{ trans('backpack::import.required_field_hint_update_only') }}");
                    badge.setAttribute('data-bs-html', 'true');
                } else {
                    // Restore normal required state
                    badge.classList.remove('required-field-badge-relaxed');
                    badge.setAttribute('title', "{{ trans('backpack::import.required_field_hint') }}");
                    badge.removeAttribute('data-bs-html');
                }
                
                // Re-initialize tooltip with HTML support when needed
                new bootstrap.Tooltip(badge, { html: isUpdateOnly });
            });
            
            requiredSelects.forEach(select => {
                const row = select.closest('.mapping-row');
                const emptyOption = select.querySelector('option[value=""]');
                
                if (isUpdateOnly) {
                    // In update-only mode, required fields are no longer mandatory
                    if (emptyOption) {
                        emptyOption.disabled = false;
                    }
                    select.classList.remove('is-invalid');
                    if (row) {
                        row.classList.remove('has-invalid-field');
                    }
                } else {
                    // In upsert mode, restore required validation
                    if (!select.value) {
                        select.classList.add('is-invalid');
                        if (row) {
                            row.classList.add('has-invalid-field');
                        }
                    }
                }
            });
            
            // Handle unique constraint warnings in update-only mode
            handleUniqueConstraintWarnings(isUpdateOnly);
            
            // Re-validate required fields
            validateRequiredFields();
        }
        
        // Handle unique constraint warnings for update-only mode
        function handleUniqueConstraintWarnings(isUpdateOnly) {
            // Get all selected matching fields
            const selectedMatchingFields = getSelectedMatchingFields();
            
            // Find all rows with unique constraints
            document.querySelectorAll('.mapping-row[data-unique-with]').forEach(row => {
                const column = row.getAttribute('data-table-column');
                const uniqueWith = row.getAttribute('data-unique-with').split(',').filter(f => f);
                const uniqueBadge = row.querySelector('.unique-constraint-badge');
                
                if (!uniqueBadge || uniqueWith.length === 0) return;
                
                // Dispose existing tooltip
                const existingTooltip = bootstrap.Tooltip.getInstance(uniqueBadge);
                if (existingTooltip) {
                    existingTooltip.dispose();
                }
                
                // Check if any of the unique constraint partners is selected as matching field
                const partnerSelected = uniqueWith.some(partner => selectedMatchingFields.includes(partner));
                // Check if this field itself is selected as matching
                const selfSelected = selectedMatchingFields.includes(column);
                // Check if ALL constraint fields are selected (complete constraint coverage)
                const allConstraintFieldsSelected = selfSelected && uniqueWith.every(partner => selectedMatchingFields.includes(partner));
                
                // Show warning if:
                // 1. We're in update-only mode AND
                // 2. At least one partner is selected but not this field (incomplete constraint)
                // OR
                // 3. This field is selected but not all partners are (incomplete constraint)
                const incompleteConstraint = (partnerSelected && !selfSelected) || (selfSelected && !allConstraintFieldsSelected);
                
                // Build the full list of constraint fields (this field + partners)
                const allConstraintFields = [column, ...uniqueWith].join(', ');
                
                if (isUpdateOnly && incompleteConstraint) {
                    // Warning: incomplete constraint coverage - potential integrity issue
                    uniqueBadge.classList.add('unique-constraint-warning');
                    const warningText = "{{ trans('backpack::import.unique_constraint_warning') }}".replace(':fields', allConstraintFields);
                    uniqueBadge.setAttribute('title', warningText);
                } else {
                    // Normal state
                    uniqueBadge.classList.remove('unique-constraint-warning');
                    const originalHint = "{{ trans('backpack::import.unique_constraint_hint') }}".replace(':fields', uniqueWith.join(', '));
                    uniqueBadge.setAttribute('title', originalHint);
                }
                
                // Re-initialize tooltip
                new bootstrap.Tooltip(uniqueBadge);
            });
        }
        
        // Add event listeners to import behavior radio buttons
        document.querySelectorAll('input[name="import_behavior"]').forEach(radio => {
            radio.addEventListener('change', handleImportBehaviorChange);
        });

        // Handle the form submission
        if (importMappingForm) {
            importMappingForm.addEventListener('submit', function(e) {
                e.preventDefault();

                // Validate that all matching fields are mapped
                const unmappedFields = getUnmappedMatchingFields();
                if (unmappedFields.length > 0) {
                    // Mark unmapped fields as invalid and scroll to first one
                    let firstInvalid = null;
                    unmappedFields.forEach(fieldName => {
                        const row = document.querySelector(`.mapping-row[data-table-column="${fieldName}"]`);
                        if (row) {
                            const select = row.querySelector('.csv-field-select, .developer-field-select');
                            if (select) {
                                select.classList.add('is-invalid');
                                if (!firstInvalid) firstInvalid = select;
                            }
                        }
                    });
                    if (firstInvalid) {
                        firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }

                // Collect matching fields
                const matchingFields = getSelectedMatchingFields();
                
                // Add hidden inputs for matching fields
                matchingFields.forEach((field, index) => {
                    const hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = `matching_fields[${index}]`;
                    hiddenInput.value = field;
                    this.appendChild(hiddenInput);
                });
                
                // Add the import behavior value to the form data
                const selectedBehavior = document.querySelector('input[name="import_behavior"]:checked');
                if (selectedBehavior) {
                    const behaviorInput = document.createElement('input');
                    behaviorInput.type = 'hidden';
                    behaviorInput.name = 'import_behavior';
                    behaviorInput.value = selectedBehavior.value;
                    this.appendChild(behaviorInput);
                }

                // Convert the reverse mapping to the original mapping expected by the backend.
                // Multiple table columns can map to the same CSV column (e.g. importo_transato and importo_transato_previsto).
                const reverseMapping = {};
                document.querySelectorAll('.csv-field-select').forEach(select => {
                    const row = select.closest('.mapping-row');
                    if (row) {
                        const tableColumn = row.getAttribute('data-table-column');
                        const csvIndex = select.value;
                        if (csvIndex) {
                            if (!reverseMapping[csvIndex]) {
                                reverseMapping[csvIndex] = [];
                            }
                            reverseMapping[csvIndex].push(tableColumn);
                        }
                    }
                });

                // Add hidden inputs: one per (csvIndex, tableColumn) so the same CSV column can fill multiple DB columns
                Object.entries(reverseMapping).forEach(([csvIndex, tableColumns]) => {
                    tableColumns.forEach(tableColumn => {
                        const hiddenMapping = document.createElement('input');
                        hiddenMapping.type = 'hidden';
                        hiddenMapping.name = `column_mapping[${csvIndex}][]`;
                        hiddenMapping.value = tableColumn;
                        this.appendChild(hiddenMapping);
                    });
                });

                // Collect all form data
                const formData = new FormData(this);

                // Show the progress panel
                $('#import-mapping-form').hide();
                $('#import-preview-results').hide();
                $('#import-progress').show();
                
                // Hide other navigation elements during import
                $('.unique-field-card').hide();
                $('.card-header').hide();
                $('.import-card').find('h3').hide();
                $('.import-card .card-header').hide();

                // Submit data via AJAX
                $.ajax({
                    url: this.action,
                    method: 'POST',
                    data: formData,
                    processData: false,
                    contentType: false,
                    xhr: function() {
                        var xhr = new XMLHttpRequest();
                        
                        // Gestisci gli aggiornamenti di progresso in tempo reale
                        xhr.onprogress = function(e) {
                            if (e.currentTarget.responseText) {
                                try {
                                    // Dividi la risposta in oggetti JSON singoli
                                    const jsonStrings = e.currentTarget.responseText.replace(/}{/g, '}|{').split('|');
                                    
                                    // Prendiamo l'ultimo oggetto JSON completo
                                    let lastValidJson = null;
                                    
                                    // Tentiamo di analizzare ogni stringa JSON separata
                                    for (let i = 0; i < jsonStrings.length; i++) {
                                        try {
                                            const jsonObj = JSON.parse(jsonStrings[i]);
                                            lastValidJson = jsonObj;
                                            
                                            // Se questo è l'oggetto finale di successo o errore
                                            if (jsonObj.status === 'success' || jsonObj.status === 'error') {
                                                break;
                                            }
                                        } catch (jsonError) {
                                            // Ignora i JSON non validi o incompleti
                                        }
                                    }
                                    
                                    if (lastValidJson) {
                                        // Aggiorna l'interfaccia con l'ultimo stato valido
                                        if (lastValidJson.status === 'processing') {
                                            // Aggiorna la barra di progresso
                                            const progressPercentage = lastValidJson.progress || 0;
                                            $('#progress-text').text(progressPercentage + '%');
                                            
                                            // Aggiorna le statistiche in tempo reale
                                            $('#total-rows').text(lastValidJson.total || 0);
                                            $('#created-rows').text(lastValidJson.created || 0);
                                            $('#updated-rows').text(lastValidJson.updated || 0);
                                            $('#skipped-rows').text(lastValidJson.skipped || 0);
                                            
                                            // Aggiorna i log di operazione se ci sono informazioni sull'operazione corrente
                                            if (lastValidJson.current_operation) {
                                                const op = lastValidJson.current_operation;
                                                
                                                // Aggiorniamo sempre il log per ogni operazione, anche se non viene inviato
                                                // un aggiornamento completo di progresso
                                                updateOperationLog(op);
                                            }
                                            
                                            // Se ci sono operazioni in batch, mostriamole tutte
                                            if (lastValidJson.operations && Array.isArray(lastValidJson.operations)) {
                                                lastValidJson.operations.forEach(op => {
                                                    updateOperationLog(op);
                                                });
                                            }
                                        } 
                                        else if (lastValidJson.status === 'success') {
                                            // L'importazione è completa, mostra i risultati
                                            // Hide import elements
                                            $('.unique-field-card').hide();
                                            $('.card-header').hide();
                                            $('.import-card').find('h3').hide();
                                            $('.import-card .card-header').hide();
                                            $('#import-mapping-form').hide();
                                            $('#import-progress').hide();
                                            $('#import-results').show();
                                            
                                            // Copiamo il log delle operazioni nella sezione dei risultati
                                            // Aggiungiamo una sezione per mostrare il log delle operazioni anche nella schermata dei risultati
                                            if (!$('#result-operation-log-container').length) {
                                                $('#result-stats').after(`
                                                    <div class="mt-4" id="result-operation-log-container">
                                                        <h5 class="text-primary mb-3">
                                                            <i class="la la-terminal me-2"></i>
                                                            {{ trans('backpack::import.operation_log_title') }}
                                                        </h5>
                                                        <div class="border rounded bg-white p-2" style="max-height: 200px; overflow-y: auto;">
                                                            <div id="result-operation-log" class="small">
                                                                ${$('#operation-log').html()}
                                                            </div>
                                                        </div>
                                                    </div>
                                                `);
                                            }
                                            
                                            // Aggiorna le statistiche finali
                                            $('#result-total-rows').text(lastValidJson.total || 0);
                                            $('#result-created-rows').text(lastValidJson.created || 0);
                                            $('#result-updated-rows').text(lastValidJson.updated || 0);
                                            $('#result-skipped-rows').text(lastValidJson.skipped || 0);
                                            $('#result-progress-text').text('100%');
                                            
                                            // Mostra il path del log se disponibile
                                            if (lastValidJson.logPath) {
                                                $('#log-path-text').text(lastValidJson.logPath);
                                                $('#log-path-alert').show();
                                            }
                                        }
                                        else if (lastValidJson.status === 'error') {
                                            // Mostra l'errore con dettagli tecnici
                                            showImportError(lastValidJson.message || "{{ trans('backpack::import.import_error') }}", lastValidJson);
                                        }
                                    }
                                } catch (e) {
                                    console.error('Errore durante l\'elaborazione degli aggiornamenti:', e);
                                }
                            }
                        };
                        
                        return xhr;
                    },
                    success: function(response) {
                        // Questa parte viene eseguita solo se la risposta è un JSON valido singolo
                        // Poiché stiamo usando l'approccio di streaming, gran parte della logica
                        // è stata spostata nell'handler "onprogress" qui sopra
                        
                        // Assicuriamoci che il log delle operazioni sia copiato nei risultati
                        if (!$('#result-operation-log-container').length) {
                            $('#result-stats').after(`
                                <div class="mt-4" id="result-operation-log-container">
                                    <h5 class="text-primary mb-3">
                                        <i class="la la-terminal me-2"></i>
                                        {{ trans('backpack::import.operation_log_title') }}
                                    </h5>
                                    <div class="border rounded bg-white p-2" style="max-height: 200px; overflow-y: auto;">
                                        <div id="result-operation-log" class="small">
                                            ${$('#operation-log').html()}
                                        </div>
                                    </div>
                                </div>
                            `);
                        }
                    },
                    error: function(xhr) {
                        // Collect detailed error information
                        let errorDetails = {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText
                        };

                        try {
                            // Try to parse the JSON response
                            if (xhr.responseText) {
                                const jsonStrings = xhr.responseText.replace(/}{/g, '}|{').split('|');
                                let lastValidJson = null;
                                
                                // Analizza l'ultimo JSON valido
                                for (let i = 0; i < jsonStrings.length; i++) {
                                    try {
                                        const jsonObj = JSON.parse(jsonStrings[i]);
                                        lastValidJson = jsonObj;
                                    } catch (e) {
                                        // Ignora gli oggetti JSON non validi
                                    }
                                }
                                
                                if (lastValidJson) {
                                    errorDetails.parsedResponse = lastValidJson;
                                    
                                    if (lastValidJson.status === 'error') {
                                        // Se abbiamo già un errore strutturato, mostralo direttamente
                                        showImportError(lastValidJson.message || "{{ trans('backpack::import.import_error') }}", lastValidJson);
                                        return;
                                    }
                                }
                            }
                        } catch (e) {
                            // If it's not JSON, use the raw text
                            errorDetails.parseError = e.message;
                        }

                        // Mostra l'errore con i dettagli disponibili
                        showImportError(xhr.responseJSON?.message || "{{ trans('backpack::import.import_error') }}", errorDetails);
                    }
                });
            });
        }

        // Initial setting of select fields to exact matching fields (if they exist)
        // Excludes developer section fields to avoid confusion
        function setInitialExactMatches() {
            // Get all mapping rows EXCLUDING developer section
            const tableColumns = Array.from(document.querySelectorAll('.mapping-row:not(.developer-field-row)')).map(row => {
                return {
                    element: row,
                    column: row.getAttribute('data-table-column'),
                    select: row.querySelector('.csv-field-select'),
                    indicator: row.querySelector('.mapping-type-indicator'),
                    isRequired: row.querySelector('.csv-field-select.required-field-select') !== null
                };
            });

            // Get all CSV headers
            const csvHeaders = Array.from(document.querySelectorAll('.csv-option')).map(option => {
                return {
                    index: option.value,
                    header: option.getAttribute('data-csv-header')
                };
            });

            // For each table column, look for an exact match
            tableColumns.forEach(tableCol => {
                const exactMatch = csvHeaders.find(csv =>
                    csv.header.toLowerCase() === tableCol.column.toLowerCase());

                // If there's an exact match, set the select value
                if (exactMatch) {
                    tableCol.select.value = exactMatch.index;
                    tableCol.select.classList.add('mapping-match-exact');
                    
                    // If this is a required field, make sure to remove the invalid state
                    if (tableCol.isRequired) {
                        tableCol.select.classList.remove('is-invalid');
                        tableCol.element.classList.remove('has-invalid-field');
                    }

                    // Also add the visual indicator
                    if (tableCol.indicator) {
                        tableCol.indicator.innerHTML = "<i class='la la-check'></i>{{ trans('backpack::import.exact_match') }}";
                        tableCol.indicator.className = 'mapping-type-indicator mapping-type-exact';
                        tableCol.indicator.classList.add('show');

                        // Hide after 3 seconds but keep visible on hover
                        setTimeout(() => {
                            tableCol.indicator.classList.add('hide-after-delay');
                            tableCol.indicator.classList.remove('show');
                        }, 3000);
                    }
                } else if (tableCol.isRequired) {
                    // If it's a required field with no match, mark it as invalid
                    tableCol.select.classList.add('is-invalid');
                    tableCol.element.classList.add('has-invalid-field');
                }
            });
            
            // After setting all matches, run validation to update button state
            validateRequiredFields();
        }

        // Run the function at page startup
        setTimeout(setInitialExactMatches, 500);

        // Initialize matching fields UI on page load
        setTimeout(function() {
            updateMatchingFieldsUI();
        }, 500);
        
        // Function to monitor import status
        function monitorImportProgress(importId) {
            const statusUrl = "{{ url($crud_route.'/import-csv/status') }}";

            // Function to update the interface with current status
            function updateProgressUI(data) {
                // Log complete data object for debugging
                
                // Update progress bar with real percentage
                const progressPercentage = Math.round((data.processed / data.total) * 100);
                $('#progress-text').text(progressPercentage + '%');

                // Update statistics
                $('#total-rows').text(data.processed);
                $('#created-rows').text(data.created || 0);
                $('#updated-rows').text(data.updated || 0);
                $('#skipped-rows').text(data.skipped || 0);

                // If import is completed
                if (data.status === 'completed') {
                    // Hide import elements
                    $('.unique-field-card').hide();
 // Correct: use ID instead of class
                    $('.card-header').hide(); // Hide the card header completely
                    $('.import-card').find('h3').hide(); // Hide the "Column Mapping" title
                    $('.import-card .card-header').hide(); // Hide the card header
                    $('#import-mapping-form').hide();
                    $('#import-progress').hide();
                    $('#import-results').show();

                    // Update final statistics
                    $('#result-total-rows').text(data.processed || 0);
                    $('#result-created-rows').text(data.created || 0);
                    $('#result-updated-rows').text(data.updated || 0);
                    $('#result-skipped-rows').text(data.skipped || 0);
                    $('#result-progress-text').text('100%');
                    
                    // Mostra il path del log se disponibile
                    if (data.logPath) {
                        $('#log-path-text').text(data.logPath);
                        $('#log-path-alert').show();
                    }

                    // Try all possible backup filename keys and log everything
                    
                    // Update the backup message to show only directory, not filename
                    $('#backup-filename').closest('.alert').find('span').html(
                        '{{ trans("backpack::import.backup_created") }} <code>/storage/backups/csv-imports/</code>'
                    );
                    
                    // Make backup container visible with a highlight effect
                    $('#backup-filename').closest('.alert').addClass('alert-highlight');
                    setTimeout(() => {
                        $('#backup-filename').closest('.alert').removeClass('alert-highlight');
                    }, 2000);

                    return true; // Import completed
                }

                // If there was an error
                if (data.status === 'error') {
                    showImportError(data.message || "{{ trans('backpack::import.import_error') }}", data);
                    return true; // Stop polling
                }

                // If the import is already completed directly
                if (data.status === 'success') {
                    // Hide import elements
                    $('.unique-field-card').hide();
 // Correct: use ID instead of class
                    $('.card-header').hide(); // Hide the card header completely
                    $('.import-card').find('h3').hide(); // Hide the "Column Mapping" title
                    $('.import-card .card-header').hide(); // Hide the card header
                    $('#import-mapping-form').hide();
                    $('#import-progress').hide();
                    $('#import-results').show();

                    // Update final statistics
                    $('#result-total-rows').text(data.total || 0);
                    $('#result-created-rows').text(data.created || 0);
                    $('#result-updated-rows').text(data.updated || 0);
                    $('#result-skipped-rows').text(data.skipped || 0);
                    $('#result-progress-text').text('100%');

                    // Try all possible backup filename keys and log everything
                    
                    // Update the backup message to show only directory, not filename
                    $('#backup-filename').closest('.alert').find('span').html(
                        '{{ trans("backpack::import.backup_created") }} <code>/storage/backups/csv-imports/</code>'
                    );
                    
                    // Make backup container visible with a highlight effect
                    $('#backup-filename').closest('.alert').addClass('alert-highlight');
                    setTimeout(() => {
                        $('#backup-filename').closest('.alert').removeClass('alert-highlight');
                    }, 2000);

                    return true; // Import completed
                }

                return false; // Continue polling
            }

            // Recursive function for status polling
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
                            // Continue polling every 2 seconds
                            setTimeout(pollStatus, 2000);
                        }
                    },
                    error: function(xhr) {
                        // Collect detailed information about the monitoring error
                        let monitorErrorDetails = {
                            status: xhr.status,
                            statusText: xhr.statusText,
                            responseText: xhr.responseText,
                            url: statusUrl
                        };

                        // Show the error with technical details
                        showImportError("{{ trans('backpack::import.import_error') }}", monitorErrorDetails);
                    }
                });
            }

            // Start polling
            pollStatus();
        }

        // Function to show import errors
        function showImportError(message, details = null) {
            $('#import-progress').hide();
            $('#import-mapping-form').hide();
            $('#import-error').show();
            
            // Extract message from details if it's an object with message property
            let errorMessage = message;
            if (details && typeof details === 'object') {
                // If details has a message and message is the same, use details.message
                if (details.message && details.message === message) {
                    errorMessage = details.message;
                } else if (details.parsedResponse && details.parsedResponse.message) {
                    errorMessage = details.parsedResponse.message;
                }
            }
            
            // Always show error details box with the message
            $('#error-details').show();
            
            // Build log text starting with the error message
            let logParts = [];
            
            // Add error message first
            logParts.push('=== MESSAGGIO ERRORE ===');
            logParts.push(errorMessage);
            logParts.push('');
            
            // Add technical details if available
            if (details) {
                let technicalDetails = {};
                if (typeof details === 'object') {
                    Object.keys(details).forEach(key => {
                        // Exclude message to avoid duplication
                        if (key !== 'message') {
                            if (key === 'parsedResponse' && details[key] && details[key].message) {
                                // Create a copy without the message
                                let parsedCopy = Object.assign({}, details[key]);
                                delete parsedCopy.message;
                                if (Object.keys(parsedCopy).length > 0) {
                                    technicalDetails[key] = parsedCopy;
                                }
                            } else {
                                technicalDetails[key] = details[key];
                            }
                        }
                    });
                } else {
                    technicalDetails = details;
                }
                
                if (Object.keys(technicalDetails).length > 0) {
                    logParts.push('=== DETTAGLI TECNICI ===');
                    logParts.push(typeof technicalDetails === 'object' ? JSON.stringify(technicalDetails, null, 2) : technicalDetails.toString());
                }
            }
            
            $('#error-log').text(logParts.join('\n'));
            
            // Show log path if available
            if (details && details.logPath) {
                $('#error-log-path-text').text(details.logPath);
                $('#error-log-path').show();
            } else if (details && details.parsedResponse && details.parsedResponse.logPath) {
                $('#error-log-path-text').text(details.parsedResponse.logPath);
                $('#error-log-path').show();
            } else {
                $('#error-log-path').hide();
            }
        }


        // Re-run the truncated text detection when a collapse type element is opened
        document.querySelectorAll('[data-bs-toggle="collapse"]').forEach(collapseButton => {
            collapseButton.addEventListener('shown.bs.collapse', function() {
                setTimeout(makeAllTruncatedElementsClickable, 300);
            });
        });
        
        // Add event handling for change on select boxes
        document.querySelectorAll('.csv-field-select').forEach(select => {
            select.addEventListener('change', function() {
                // Remove all style classes for the match
                this.classList.remove('mapping-match-exact', 'mapping-match-similar', 'mapping-match-none');

                // Hide the matching box and mark as user modified
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

        // Function to validate required fields and update the status of the submit button
        function validateRequiredFields() {
            // Check if update-only mode is active
            const isUpdateOnly = document.getElementById('behavior-update-only')?.checked || false;
            
            // Only validate required fields in main table (not in matching section)
            let requiredFields = document.querySelectorAll('#main-mapping-table .required-field-select');
            let missingRequiredFields = [];
            let isValid = true;
            
            requiredFields.forEach(function(field) {
                const row = field.closest('.mapping-row');
                
                // In update-only mode, required fields are not mandatory
                if (isUpdateOnly) {
                    field.classList.remove('is-invalid');
                    if (row) {
                        row.classList.remove('has-invalid-field');
                    }
                    return;
                }
                
                // Field is invalid only when "Do not import" is selected (empty value)
                if (!field.value) {
                    field.classList.add('is-invalid');
                    row.classList.add('has-invalid-field');
                    
                    // Get the field name from the mapping row
                    let fieldName = row.querySelector('.table-column .fw-medium').textContent.trim();
                    missingRequiredFields.push(fieldName);
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                    row.classList.remove('has-invalid-field');
                }
            });
            
            // Update the submit button status
            const submitButton = document.getElementById('start-import');
            if (!isValid) {
                submitButton.classList.add('disabled-import-btn');
                submitButton.disabled = true;
                // Add a tooltip to the button
                submitButton.setAttribute('data-bs-toggle', 'tooltip');
                submitButton.setAttribute('data-bs-placement', 'top');
                submitButton.setAttribute('title', "{{ trans('backpack::import.required_fields_tooltip') }}");
            } else {
                submitButton.classList.remove('disabled-import-btn');
                submitButton.disabled = false;
                submitButton.removeAttribute('data-bs-toggle');
                submitButton.removeAttribute('title');
            }
            
            return { isValid, missingRequiredFields };
        }
        
        // Add event listener to each required field to validate on change
        document.querySelectorAll('.required-field-select').forEach(select => {
            select.addEventListener('change', function() {
                const row = this.closest('.mapping-row');
                
                // Remove the invalid styling when a valid option is selected
                if (this.value) {
                    this.classList.remove('is-invalid');
                    row.classList.remove('has-invalid-field');
                } else {
                    this.classList.add('is-invalid');
                    row.classList.add('has-invalid-field');
                }
                
                validateRequiredFields();
            });
            
            // Trigger the change event to initialize the validation state
            select.dispatchEvent(new Event('change'));
        });
        
        // Verify required fields before form submission
        document.getElementById('import-mapping-form').addEventListener('submit', function(e) {
            const validation = validateRequiredFields();
            
            if (!validation.isValid) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: "{{ trans('backpack::import.required_fields_missing') }}",
                    html: "{{ trans('backpack::import.required_fields_message') }}<br><br><ul><li>" + validation.missingRequiredFields.join('</li><li>') + '</li></ul>',
                    confirmButtonText: "{{ trans('backpack::crud.ok') }}",
                    confirmButtonColor: '#d33'
                });
                return false;
            }
            
            return true;
        });

        // Initialize tooltips on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Toggle password badge (Auto-generated / Vuota) and tooltip when "generate passwords" is unchecked (User import only)
        const generatePasswordsCheckbox = document.getElementById('generate_passwords');
        const passwordFieldBadge = document.getElementById('password-field-badge');
        if (generatePasswordsCheckbox && passwordFieldBadge) {
            const titleAuto = passwordFieldBadge.getAttribute('data-title-auto') || '';
            const titleEmpty = passwordFieldBadge.getAttribute('data-title-empty') || '';
            const textAuto = passwordFieldBadge.getAttribute('data-text-auto') || '';
            const textEmpty = passwordFieldBadge.getAttribute('data-text-empty') || '';

            function updatePasswordBadge() {
                const checked = generatePasswordsCheckbox.checked;
                var tooltipContent = checked ? titleAuto : titleEmpty;
                if (checked) {
                    passwordFieldBadge.classList.remove('password-badge-empty');
                    passwordFieldBadge.classList.add('bg-success-subtle', 'text-success-emphasis');
                    passwordFieldBadge.innerHTML = '<i class="la la-magic password-badge-icon"></i> <span class="password-badge-text">' + escapeHtml(textAuto) + '</span>';
                } else {
                    passwordFieldBadge.classList.remove('bg-success-subtle', 'text-success-emphasis');
                    passwordFieldBadge.classList.add('password-badge-empty');
                    passwordFieldBadge.innerHTML = '<i class="la la-exclamation-triangle password-badge-icon"></i> <span class="password-badge-text">' + escapeHtml(textEmpty) + '</span>';
                }
                passwordFieldBadge.setAttribute('title', tooltipContent);
                if (passwordFieldBadge.hasAttribute('data-bs-title')) { passwordFieldBadge.setAttribute('data-bs-title', tooltipContent); }
                var tip = bootstrap.Tooltip.getInstance(passwordFieldBadge);
                if (tip) { tip.dispose(); }
                new bootstrap.Tooltip(passwordFieldBadge);
            }
            function escapeHtml(str) {
                const div = document.createElement('div');
                div.textContent = str;
                return div.innerHTML;
            }
            generatePasswordsCheckbox.addEventListener('change', updatePasswordBadge);
            updatePasswordBadge();
        }

        // Developer Section toggle functionality
        const developerToggle = document.getElementById('enable-developer-fields');
        if (developerToggle) {
            developerToggle.addEventListener('change', function() {
                // Only affect developer rows that are still in the developer section (not moved to matching)
                const developerTableTbody = document.querySelector('.developer-fields-table tbody');
                const developerTable = document.querySelector('.developer-fields-table');
                
                if (this.checked) {
                    // Enable developer fields with confirmation
                    if (confirm("{{ trans('backpack::import.developer_confirm_enable') }}")) {
                        if (developerTableTbody) {
                            developerTableTbody.querySelectorAll('.developer-field-row').forEach(row => {
                                const select = row.querySelector('.developer-field-select');
                                if (select) {
                                    select.disabled = false;
                                    select.classList.remove('disabled-select');
                                }
                                row.classList.add('developer-enabled');
                            });
                        }
                        if (developerTable) {
                            developerTable.classList.add('enabled');
                        }
                    } else {
                        // User cancelled, revert toggle
                        this.checked = false;
                    }
                } else {
                    // Disable developer fields and reset values (only those in developer section)
                    if (developerTableTbody) {
                        developerTableTbody.querySelectorAll('.developer-field-row').forEach(row => {
                            const select = row.querySelector('.developer-field-select');
                            if (select) {
                                select.disabled = true;
                                select.value = '';
                                select.classList.add('disabled-select');
                            }
                            row.classList.remove('developer-enabled');
                        });
                    }
                    if (developerTable) {
                        developerTable.classList.remove('enabled');
                    }
                }
            });
        }

        // Funzione per aggiornare il log delle operazioni
        function updateOperationLog(op) {
            // Crea un ID univoco per questa operazione in base a tipo e ID
            const operationUniqueId = op.action + '_' + (op.id || '') + '_row' + op.row;
            
            // Verifica se questa operazione è già stata registrata
            if (processedOperationIds.has(operationUniqueId)) {
                return; // Ignora operazioni duplicate
            }
            
            // Registra questa operazione come elaborata
            processedOperationIds.add(operationUniqueId);
            
            let logMessage = '';
            let operationTitle = '';
            let operationDetails = '';
            let opIcon = 'la-info-circle';
            let logClass = '';
            let iconClass = 'text-primary';
            
            // Costruisci messaggio per il log compatto
            logMessage = `<div class="log-entry py-1 border-bottom" data-op-id="${operationUniqueId}">
                <span class="text-muted me-1">${new Date().toLocaleTimeString('it-IT', {hour: '2-digit', minute:'2-digit', second:'2-digit'})}</span>`;
            
            // Imposta titolo, dettagli e icona in base all'azione
            if (op.action === 'insert') {
                logClass = 'text-success';
                iconClass = 'text-success';
                opIcon = 'la-plus-circle';
                operationTitle = "{{ trans('backpack::import.record_inserted') }}".replace(':primary_key', op.primary_key).replace(':primary_key_value', op.primary_key_value);
                
                if (op.field && op.value) {
                    operationDetails = "{{ trans('backpack::import.processing_value') }}"
                        .replace(':value', op.value)
                        .replace(':field', op.field);
                }
                
                logMessage += `<span class="${logClass}"><i class="la la-plus-circle me-1"></i> ${operationTitle}</span>`;
            } 
            else if (op.action === 'update') {
                logClass = 'text-primary';
                iconClass = 'text-primary';
                opIcon = 'la-sync';
                operationTitle = "{{ trans('backpack::import.record_updated') }}".replace(':primary_key', op.primary_key).replace(':primary_key_value', op.primary_key_value);
                
                if (op.field && op.value) {
                    operationDetails = "{{ trans('backpack::import.processing_value') }}"
                        .replace(':value', op.value)
                        .replace(':field', op.field);
                }
                
                logMessage += `<span class="${logClass}"><i class="la la-sync me-1"></i> ${operationTitle}</span>`;
            } 
            else if (op.action === 'skip') {
                logClass = 'text-warning';
                iconClass = 'text-warning';
                opIcon = 'la-ban';
                operationTitle = "{{ trans('backpack::import.record_skipped') }}";
                
                if (op.reason === 'update_only_mode') {
                    operationDetails = "{{ trans('backpack::import.update_only_reason') }}";
                } else if (op.reason === 'user_not_found') {
                    operationDetails = "{{ trans('backpack::import.skip_reason_user_not_found') }}";
                }
                
                logMessage += `<span class="${logClass}"><i class="la la-ban me-1"></i> ${operationTitle}</span>`;
            }
            
            // Aggiungi informazioni sulla riga al messaggio di log
            const rowText = "{{ trans('backpack::import.row_processing') }}".replace(':row', op.row);
            logMessage += ` <span class="text-muted">- ${rowText}</span>`;
            
            // Aggiungi messaggio se la password è stata auto-generata
            if (op.password_auto_generated) {
                logMessage += ` <span class="text-success"><i class="la la-key me-1"></i> Password auto-generata e hashata</span>`;
            }
            
            logMessage += `</div>`;
            
            // Aggiorna il box dell'operazione corrente
            $('#current-op-icon').removeClass().addClass(`la ${opIcon} fs-2 ${iconClass}`);
            $('#current-op-title').html(operationTitle);
            $('#current-op-details').html(operationDetails);
            
            // Aggiungi la voce di log e scorri automaticamente in basso
            const logElement = $('#operation-log');
            logElement.prepend(logMessage); // Prepend per avere le voci più recenti in alto
            
            // Aggiungi effetto pulse
            const currentOpBox = $('#current-operation-box');
            currentOpBox.removeClass('pulse-animation');
            void currentOpBox[0].offsetWidth; // Trigger reflow
            currentOpBox.addClass('pulse-animation');
        }

        // ─── Import Preview functionality ───

        function collectImportFormData() {
            const form = document.getElementById('import-mapping-form');
            const formData = new FormData(form);

            // Collect column_mapping (reverse mapping: db_column => csv_index)
            const columnMapping = {};
            const reverseMapping = formData.getAll('column_mapping_reverse[]');
            document.querySelectorAll('select[name^="column_mapping_reverse"]').forEach(select => {
                const name = select.name;
                const match = name.match(/column_mapping_reverse\[(.+)\]/);
                if (match && select.value) {
                    const dbColumn = match[1];
                    const csvIndex = parseInt(select.value);
                    if (!columnMapping[csvIndex]) {
                        columnMapping[csvIndex] = dbColumn;
                    } else if (Array.isArray(columnMapping[csvIndex])) {
                        columnMapping[csvIndex].push(dbColumn);
                    } else {
                        columnMapping[csvIndex] = [columnMapping[csvIndex], dbColumn];
                    }
                }
            });

            // Collect matching fields
            const matchingFields = [];
            document.querySelectorAll('.matching-field-select').forEach(select => {
                if (select.value) matchingFields.push(select.value);
            });

            return {
                file_path: formData.get('file_path'),
                column_mapping: columnMapping,
                matching_fields: matchingFields,
                delimiter: formData.get('delimiter'),
                import_behavior: document.querySelector('input[name="import_behavior"]:checked')?.value || 'update_insert',
            };
        }

        function renderPreviewResults(data) {
            const s = data.summary;
            const meta = data.meta || {};
            const pk = meta.primary_key || 'id';
            const uniqueCols = meta.unique_columns || [];
            const alwaysShowCols = new Set([pk, ...uniqueCols]);

            $('#preview-total-rows').text(s.total_rows);
            $('#preview-will-insert').text(s.will_insert);
            $('#preview-will-update').text(s.will_update);
            $('#preview-will-skip').text(s.will_skip);

            // Count errors and warnings
            const errorCount = data.warnings.filter(w => w.severity === 'error').length;
            const warningCount = data.warnings.filter(w => w.severity === 'warning').length;

            // Status badge
            const badge = $('#preview-status-badge');
            badge.removeClass('bg-info bg-success bg-warning bg-danger');
            if (errorCount > 0) {
                badge.addClass('bg-danger').text(errorCount + ' errori, ' + warningCount + ' avvisi');
            } else if (warningCount > 0) {
                badge.addClass('bg-warning').text(warningCount + ' avvisi');
            } else {
                badge.addClass('bg-success').text('Nessun problema');
            }

            // Block or allow import based on errors
            const confirmBtn = $('#preview-confirm-btn');
            if (errorCount > 0) {
                confirmBtn.prop('disabled', true);
            } else {
                confirmBtn.prop('disabled', false);
            }

            // Group warnings by row number
            const warningsByRow = {};
            data.warnings.forEach(function(w) {
                if (w.row > 0) {
                    if (!warningsByRow[w.row]) warningsByRow[w.row] = [];
                    warningsByRow[w.row].push(w);
                }
            });

            // Render main rows table: # | PK + unique cols | Dati (inline changes) | Azione
            // Rows with problems come first, then the rest
            const thead = document.getElementById('preview-rows-thead');
            thead.innerHTML = '';

            // Build header
            const pkCols = Array.from(alwaysShowCols);
            let theadHtml = '<tr><th style="font-size:0.75rem">#</th>';
            pkCols.forEach(function(col) {
                theadHtml += '<th class="text-nowrap">' + escapeHtml(col) + '</th>';
            });
            theadHtml += '<th>Dati</th><th>Azione</th></tr>';
            thead.innerHTML = theadHtml;

            $('#preview-rows-total-count').text(data.rows.length);

            // Sort rows: problem rows first (by row number), then non-problem rows
            const sortedRows = data.rows.slice().sort(function(a, b) {
                const aHasProblem = !!warningsByRow[a.row];
                const bHasProblem = !!warningsByRow[b.row];
                if (aHasProblem && !bHasProblem) return -1;
                if (!aHasProblem && bHasProblem) return 1;
                return a.row - b.row;
            });

            // Pagination state
            let currentPage = 1;
            let pageSize = parseInt($('#preview-page-size').val()) || 100;
            let totalPages = Math.ceil(sortedRows.length / pageSize);

            function renderPage() {
                const tbody = document.getElementById('preview-rows-tbody');
                const startIdx = (currentPage - 1) * pageSize;
                const endIdx = Math.min(startIdx + pageSize, sortedRows.length);
                const pageRows = sortedRows.slice(startIdx, endIdx);

                // Build all rows as single HTML string for performance
                let rowsHtml = '';
                pageRows.forEach(function(r) {
                    // Row number: becomes a badge with tooltip if there are problems
                    let rowNumHtml;
                    if (warningsByRow[r.row]) {
                        const hasError = warningsByRow[r.row].some(w => w.severity === 'error');
                        const tooltipText = warningsByRow[r.row].map(w => escapeHtml(w.type + ': ' + w.message)).join('&#10;');
                        if (hasError) {
                            rowNumHtml = '<td><span class="badge bg-danger-subtle text-danger-emphasis preview-error-badge" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-html="true" title="' + tooltipText + '"><i class="la la-exclamation-circle me-1"></i>' + r.row + '</span></td>';
                        } else {
                            rowNumHtml = '<td><span class="badge bg-warning-subtle text-warning-emphasis preview-warning-badge" data-bs-toggle="tooltip" data-bs-placement="right" data-bs-html="true" title="' + tooltipText + '"><i class="la la-exclamation-triangle me-1"></i>' + r.row + '</span></td>';
                        }
                    } else {
                        rowNumHtml = '<td class="text-muted" style="font-size:0.75rem">' + r.row + '</td>';
                    }

                    let trHtml = '<tr>' + rowNumHtml;

                    // PK + unique columns
                    pkCols.forEach(function(col) {
                        const val = r.data[col] !== undefined ? r.data[col] : '';
                        trHtml += '<td class="small fw-bold">' + escapeHtml(String(val)) + '</td>';
                    });

                    // Dati column: inline changed fields with old→new
                    let datiStr = '';
                    let changedCount = 0;
                    Object.entries(r.data).forEach(function(e) {
                        const col = e[0];
                        const val = e[1];
                        if (col.endsWith('_old') || alwaysShowCols.has(col)) return;

                        const oldVal = r.data[col + '_old'];
                        const isChanged = r.changed_fields && r.changed_fields.includes(col);

                        if (isChanged && oldVal !== undefined) {
                            datiStr += '<span class="d-inline-block me-2">' +
                                '<span class="text-muted small">' + escapeHtml(col) + '</span>: ' +
                                '<span class="text-decoration-line-through text-danger small">' + escapeHtml(String(oldVal)) + '</span>' +
                                '<span class="text-muted mx-1">→</span>' +
                                '<span class="text-success fw-bold small">' + escapeHtml(String(val)) + '</span>' +
                                '</span>';
                            changedCount++;
                        } else if (r.action === 'insert' || r.action === 'skip') {
                            datiStr += '<span class="d-inline-block me-2">' +
                                '<span class="text-muted small">' + escapeHtml(col) + '</span>: ' +
                                '<span class="text-dark small">' + escapeHtml(String(val)) + '</span>' +
                                '</span>';
                        }
                    });
                    if (r.action === 'update' && changedCount === 0) {
                        datiStr = '<span class="text-muted small">Nessuna modifica</span>';
                    }
                    trHtml += '<td class="small">' + datiStr + '</td>';

                    // Action at end (no warning icon)
                    const actionClass = r.action === 'insert' ? 'success' : (r.action === 'update' ? 'primary' : 'warning');
                    const actionLabel = r.action === 'insert' ? 'Nuovo' : (r.action === 'update' ? 'Aggiorna' : 'Salta');
                    trHtml += '<td><span class="badge bg-' + actionClass + '-subtle text-' + actionClass + '-emphasis">' + actionLabel + '</span></td>';

                    trHtml += '</tr>';
                    rowsHtml += trHtml;
                });
                tbody.innerHTML = rowsHtml;

                // Update pagination controls
                $('#preview-rows-shown-count').text(endIdx - startIdx);
                $('#preview-page-num').text(currentPage);
                $('#preview-page-info').text('Righe ' + (startIdx + 1) + '–' + endIdx + ' di ' + sortedRows.length);
                $('#preview-prev-page').prop('disabled', currentPage <= 1);
                $('#preview-next-page').prop('disabled', currentPage >= totalPages);
                $('#preview-pagination').toggle(totalPages > 1);

                // Initialize tooltips on current page
                $('#preview-rows-tbody [data-bs-toggle="tooltip"]').each(function() {
                    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
                        new bootstrap.Tooltip(this);
                    }
                });

                // Scroll table to top on page change
                $('#preview-rows-table').closest('div[style*="max-height"]').scrollTop(0);
            }

            // Page size change handler
            $('#preview-page-size').off('change').on('change', function() {
                pageSize = parseInt($(this).val()) || 100;
                totalPages = Math.ceil(sortedRows.length / pageSize);
                currentPage = 1;
                renderPage();
            });

            // Prev/Next handlers
            $('#preview-prev-page').off('click').on('click', function() {
                if (currentPage > 1) { currentPage--; renderPage(); }
            });
            $('#preview-next-page').off('click').on('click', function() {
                if (currentPage < totalPages) { currentPage++; renderPage(); }
            });

            // Initial render
            renderPage();

            // Show preview section
            $('#import-preview-results').slideDown();
            $('html, body').animate({ scrollTop: $('#import-preview-results').offset().top - 50 }, 500);
        }

        function escapeHtml(str) {
            const div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        // Start import button → triggers mandatory preview first
        $('#start-import').on('click', function() {
            const btn = $(this);
            const formData = collectImportFormData();

            if (Object.keys(formData.column_mapping).length === 0) {
                alert('Nessuna colonna mappata. Configurare il mapping prima di procedere.');
                return;
            }

            btn.prop('disabled', true).html('<i class="la la-spinner fa-spin"></i> Analisi in corso...');

            $.ajax({
                url: '{{ url($crud_route . "/import-csv/preview") }}',
                method: 'POST',
                data: JSON.stringify(formData),
                contentType: 'application/json',
                headers: { 'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content') || '{{ csrf_token() }}' },
                success: function(response) {
                    // Hide mapping form and card, show only preview
                    $('#import-mapping-form').hide();
                    $('.unique-field-card').hide();
                    $('.import-card .card-header').hide();
                    renderPreviewResults(response);
                },
                error: function(xhr) {
                    let msg = 'Errore durante l\'analisi dell\'anteprima.';
                    if (xhr.responseJSON && xhr.responseJSON.message) {
                        msg += '\n' + xhr.responseJSON.message;
                    }
                    alert(msg);
                },
                complete: function() {
                    btn.prop('disabled', false).html('<i class="la la-eye"></i> Mostra anteprima');
                }
            });
        });

        // Close preview button → show mapping again
        $('#preview-close-btn').on('click', function() {
            $('#import-preview-results').slideUp();
            $('#import-mapping-form').show();
            $('.unique-field-card').show();
            $('.import-card .card-header').show();
        });

        // Warning filter buttons
        $(document).on('click', '.preview-filter-btn', function() {
            $('.preview-filter-btn').removeClass('active');
            $(this).addClass('active');
            const filter = $(this).data('filter');
            if (filter === 'all') {
                $('#preview-warnings-list .alert').show();
            } else {
                $('#preview-warnings-list .alert').hide();
                $('#preview-warnings-list .alert[data-severity="' + filter + '"]').show();
            }
        });
    });
</script>
@endpush