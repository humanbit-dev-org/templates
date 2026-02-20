@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        {!! trans('backpack::import.import_csv', ['name' => $crud]) !!}
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card import-card">
            <div class="card-body">
                <form method="POST" action="{{ url($crud_route.'/import-csv/analyze') }}" enctype="multipart/form-data" class="import-form">
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
                        <label for="csv_file" class="import-label">
                            <i class="la la-file-csv"></i>
                            {{ trans('backpack::import.select_file') }}
                        </label>
                        <div class="custom-file-upload" id="upload-area">
                            <input type="file" name="csv_file" id="csv_file" class="form-control-file csv-file-input" accept=".csv,.txt" required>
                            <input type="hidden" name="client_last_modified" id="client_last_modified">
                            <div class="file-upload-placeholder" id="file-upload-placeholder">
                                <i class="la la-cloud-upload-alt"></i>
                                <span>{{ trans('backpack::import.drag_drop_or_select') }}</span>
                            </div>
                            <div class="selected-file" id="selected-file">
                                <i class="la la-file-csv"></i>
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
        const fileInput = document.getElementById('csv_file');
        const fileLabel = document.getElementById('file-name');
        const filePlaceholder = document.getElementById('file-upload-placeholder');
        const selectedFile = document.getElementById('selected-file');
        const removeButton = document.getElementById('remove-file');
        const uploadArea = document.getElementById('upload-area');

        // Initially hide the selected file container
        selectedFile.style.display = 'none';

        // Disable event propagation for the remove button
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

        // Handle file change
        fileInput.addEventListener('change', function() {
            if (fileInput.files.length > 0) {
                const f = fileInput.files[0];
                showSelectedFile(f.name);
                // Capture client-side lastModified (ms since epoch) and convert to ISO
                if (f.lastModified) {
                    const dt = new Date(f.lastModified);
                    document.getElementById('client_last_modified').value = dt.toISOString();
                }
            } else {
                // When the user cancels the file selection, reset the input
                resetFileInput();
            }
        });

        // Function to show the selected file
        function showSelectedFile(fileName) {
            fileLabel.textContent = fileName;

            // Restore the opacity to original value
            selectedFile.style.opacity = '1';

            // Apply compact style immediately
            uploadArea.classList.add('has-file');
            selectedFile.style.display = 'flex';
            filePlaceholder.style.display = 'none';
        }

        // Function to reset the file input
        function resetFileInput() {
            fileInput.value = '';
            fileLabel.textContent = '';

            // Animation to expand the upload box
            uploadArea.classList.remove('has-file');
            // First hide the selected file with fade-out
            selectedFile.style.opacity = '0';
            setTimeout(() => {
                selectedFile.style.display = 'none';
                // Then show the placeholder with fade-in
                filePlaceholder.style.display = 'flex';
                filePlaceholder.style.opacity = '0';
                setTimeout(() => {
                    filePlaceholder.style.opacity = '1';
                }, 50);
            }, 200);
        }

        // Handle drag and drop
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

        // Stop event propagation from the selected file container to the input
        selectedFile.addEventListener('click', function(e) {
            if (e.target.closest('.btn-remove-file')) {
                e.preventDefault();
                e.stopPropagation();
                resetFileInput();
                return false;
            }
        });

        // Also block mousedown events to avoid focus issues
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