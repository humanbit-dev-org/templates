@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        {{ trans('backpack::crud.xlsx_import') }} - {{ $crud }}
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card import-card">
            <div class="card-body">
                <form method="POST" action="{{ url($crud_route.'/import-xlsx/analyze') }}" enctype="multipart/form-data" class="import-form">
                    @csrf

                    @if($errors->any())
                    <div class="alert alert-danger">
                        <ul class="list-unstyled">
                            @foreach($errors->all() as $error)
                            <li><i class="la la-info-circle"></i> {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif

                    <div class="alert alert-info bg-light rounded p-4 mb-4" style="display: block !important;">
                        <h5 class="text-info mb-3 fw-bold w-100" style="display: block !important; width: 100% !important;">{{ trans('backpack::import.what_you_need_to_know') }}</h5>
                        <ul class="mb-0 ps-0 list-unstyled" style="display: block !important; width: 100% !important;">
                            <li class="mb-2">
                                <i class="la la-check-circle text-success me-2"></i>
                                {{ trans('backpack::import.info_csv_format') }}
                            </li>
                            <li class="mb-2">
                                <i class="la la-check-circle text-success me-2"></i>
                                {{ trans('backpack::import.info_backup_log') }}
                            </li>
                            <li>
                                <i class="la la-check-circle text-success me-2"></i>
                                {{ trans('backpack::import.info_rollback') }}
                            </li>
                        </ul>
                    </div>

                    <div class="form-group csv-upload-container">
                        <label for="xlsx_file" class="import-label">
                            <i class="la la-file-excel"></i>
                            {{ trans('backpack::import.select_file') }}
                        </label>
                        <div class="custom-file-upload" id="upload-area">
                            <input type="file" name="xlsx_file" id="xlsx_file" class="form-control-file csv-file-input" accept=".xlsx,.xls" required>
                            <input type="hidden" name="client_last_modified" id="client_last_modified">
                            <div class="file-upload-placeholder" id="file-upload-placeholder">
                                <i class="la la-cloud-upload-alt"></i>
                                <span>{{ trans('backpack::import.drag_drop_or_select') }}</span>
                            </div>
                            <div class="selected-file" id="selected-file">
                                <i class="la la-file-excel"></i>
                                <span id="file-name"></span>
                                <button type="button" class="btn-remove-file" id="remove-file" aria-label="Remove file">
                                    <i class="la la-times-circle"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mt-4 import-form-actions">
                        <a href="{{ url($crud_route) }}" class="btn btn-outline-secondary text-decoration-none import-cancel-btn">
                            <i class="la la-ban"></i>
                            {{ trans('backpack::import.cancel') }}
                        </a>
                        <button type="submit" class="btn btn-primary import-submit-btn">
                            <i class="la la-cog"></i>
                            {{ trans('backpack::import.analyze_file') }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

@push('after_scripts')
<script>
    $(document).ready(function() {
        const fileInput = document.getElementById('xlsx_file');
        const fileLabel = document.getElementById('file-name');
        const filePlaceholder = document.getElementById('file-upload-placeholder');
        const selectedFile = document.getElementById('selected-file');
        const removeButton = document.getElementById('remove-file');
        const uploadArea = document.getElementById('upload-area');

        selectedFile.style.display = 'none';

        removeButton.addEventListener('pointerdown', function(e) {
            e.preventDefault();
            e.stopPropagation();
        });

        removeButton.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            resetFileInput();
            return false;
        });

        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                const f = fileInput.files[0];
                showSelectedFile(f.name);
                if (f.lastModified) {
                    const dt = new Date(f.lastModified);
                    document.getElementById('client_last_modified').value = dt.toISOString();
                }
            } else {
                resetFileInput();
            }
        });

        function showSelectedFile(fileName) {
            fileLabel.textContent = fileName;
            selectedFile.style.opacity = '1';
            uploadArea.classList.add('has-file');
            selectedFile.style.display = 'flex';
            filePlaceholder.style.display = 'none';
        }

        function resetFileInput() {
            fileInput.value = '';
            fileLabel.textContent = '';
            uploadArea.classList.remove('has-file');
            selectedFile.style.opacity = '0';
            setTimeout(() => {
                selectedFile.style.display = 'none';
                filePlaceholder.style.display = 'flex';
                filePlaceholder.style.opacity = '0';
                setTimeout(() => {
                    filePlaceholder.style.opacity = '1';
                }, 50);
            }, 200);
        }

        const dropArea = document.querySelector('.custom-file-upload');

        ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, preventDefaults, false);
        });

        function preventDefaults(e) {
            e.preventDefault();
            e.stopPropagation();
        }

        ['dragenter', 'dragover'].forEach(eventName => {
            dropArea.addEventListener(eventName, function() {
                dropArea.classList.add('highlight');
            }, false);
        });

        ['dragleave', 'drop'].forEach(eventName => {
            dropArea.addEventListener(eventName, function() {
                dropArea.classList.remove('highlight');
            }, false);
        });

        dropArea.addEventListener('drop', function(e) {
            const dt = e.dataTransfer;
            const files = dt.files;

            if (files.length > 0) {
                fileInput.files = files;
                showSelectedFile(files[0].name);
            }
        }, false);

        selectedFile.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-file')) {
                e.preventDefault();
                e.stopPropagation();
                resetFileInput();
                return false;
            }
        });

        selectedFile.addEventListener('mousedown', function(e) {
            if (e.target.closest('.btn-remove-file')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });
</script>
@endpush
