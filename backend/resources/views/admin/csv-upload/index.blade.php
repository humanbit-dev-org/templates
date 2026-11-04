@extends(backpack_view('blank'))

@section('header')
<section class="header-operation container-fluid animated fadeIn d-flex mb-2 align-items-baseline d-print-none" bp-section="page-header">
    <h1 class="text-capitalize mb-0" bp-section="page-heading">
        <i class="la la-upload"></i> Upload File CSV
    </h1>
</section>
@endsection

@section('content')
<div class="row">
    <div class="col-md-12">
        <div class="card import-card">
            <div class="card-body">
                <form method="POST" action="{{ backpack_url('csv-upload/upload') }}" enctype="multipart/form-data" class="import-form" id="upload-form">
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

                    <div class="alert alert-info bg-light rounded p-4 mb-4 csv-upload-info-alert">
                        <h5 class="text-info mb-3 fw-bold w-100">
                            <i class="la la-info-circle"></i> Informazioni Importazione CSV
                        </h5>
                        <ul class="mb-0 ps-0 list-unstyled">
                            <li class="mb-2">
                                <i class="la la-check-circle text-success me-2"></i>
                                La tipologia viene rilevata automaticamente dal nome del file
                            </li>
                            <li class="mb-2">
                                <i class="la la-check-circle text-success me-2"></i>
                                Se il rilevamento automatico non funziona, seleziona manualmente la tipologia
                            </li>
                            <li>
                                <i class="la la-check-circle text-success me-2"></i>
                                Il file verrà salvato nella directory corrispondente per l'elaborazione automatica
                            </li>
                        </ul>
                    </div>

                    <div class="form-group csv-upload-container">
                        <label for="csv_file" class="import-label">
                            <i class="la la-file-csv"></i>
                            Seleziona File CSV
                        </label>
                        <div class="custom-file-upload" id="upload-area">
                            <input type="file" name="file" id="csv_file" class="form-control-file csv-file-input" accept=".csv,.txt" required>
                            <div class="file-upload-placeholder" id="file-upload-placeholder">
                                <i class="la la-cloud-upload-alt"></i>
                                <span>Trascina il file qui o clicca per selezionare</span>
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

                    <div class="form-group mt-4 type-selection-container" id="type-selection-container">
                        <label for="type" class="import-label">
                            <i class="la la-list"></i>
                            Tipologia di Import <span class="text-danger">*</span>
                        </label>
                        <select name="type" id="type" class="form-control @error('type') is-invalid @enderror">
                            <option value="">-- Seleziona una tipologia --</option>
                            @foreach($types as $key => $type)
                                <option value="{{ $key }}" {{ old('type') == $key ? 'selected' : '' }}>
                                    {{ $type['name'] }}
                                </option>
                            @endforeach
                        </select>
                        @error('type')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                        <small class="form-text text-muted mt-2">
                            <i class="la la-info-circle"></i> Seleziona manualmente la tipologia se il rilevamento automatico non ha funzionato
                        </small>
                    </div>

                    <div class="form-group mt-4 detected-type-info" id="detected-type-info">
                        <div class="alert alert-success">
                            <i class="la la-check-circle"></i>
                            <strong>Tipologia rilevata:</strong> <span id="detected-type-name"></span>
                        </div>
                    </div>

                    <div class="form-group mt-4 import-form-actions">
                        <a href="{{ backpack_url('dashboard') }}" class="btn btn-outline-secondary text-decoration-none import-cancel-btn">
                            <i class="la la-ban"></i>
                            Annulla
                        </a>
                        <button type="submit" class="btn btn-primary import-submit-btn">
                            <i class="la la-upload"></i>
                            Carica File
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card mt-4">
            <div class="card-header">
                <h3 class="card-title mb-0">
                    <i class="la la-info-circle"></i> Tipologie di Import Disponibili
                </h3>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered csv-types-table">
                        <thead>
                            <tr>
                                <th>Tipologia</th>
                                <th>Operazione</th>
                                <th>Directory di Destinazione</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($types as $key => $type)
                                <tr data-type-key="{{ $key }}" class="type-row">
                                    <td><strong>{{ $type['name'] }}</strong></td>
                                    <td>{{ $type['description'] }}</td>
                                    <td><code>database/import/{{ $type['directory'] }}/</code></td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="text-muted">
                                        Nessuna tipologia configurata: aggiungile in <code>config/import.php</code> ('upload_types').
                                    </td>
                                </tr>
                            @endforelse
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
    $(document).ready(function() {
        const fileInput = document.getElementById('csv_file');
        const fileLabel = document.getElementById('file-name');
        const filePlaceholder = document.getElementById('file-upload-placeholder');
        const selectedFile = document.getElementById('selected-file');
        const removeButton = document.getElementById('remove-file');
        const uploadArea = document.getElementById('upload-area');
        const typeSelectionContainer = document.getElementById('type-selection-container');
        const detectedTypeInfo = document.getElementById('detected-type-info');
        const detectedTypeName = document.getElementById('detected-type-name');
        const typeSelect = document.getElementById('type');

        // Mapping delle tipologie per il rilevamento automatico
        const typeMapping = @json($types);
        
        // Pattern di rilevamento (prefisso nome file => tipologia), derivati da
        // config/import.php 'upload_types', gli stessi usati lato server.
        const detectionPatterns = {};
        Object.entries(typeMapping).forEach(([key, type]) => {
            (type.patterns || []).forEach((pattern) => { detectionPatterns[pattern] = key; });
        });

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
                detectTypeFromFileName(f.name);
            } else {
                resetFileInput();
            }
        });
        
        // Handle manual type selection
        typeSelect.addEventListener('change', function() {
            const selectedValue = this.value;
            if (selectedValue) {
                highlightTableRow(selectedValue);
            } else {
                removeTableRowHighlight();
            }
        });

        // Function to detect type from filename
        function detectTypeFromFileName(fileName) {
            let detectedTypeKey = null;
            
            // Check patterns
            for (const [pattern, typeKey] of Object.entries(detectionPatterns)) {
                if (fileName.startsWith(pattern)) {
                    detectedTypeKey = typeKey;
                    break;
                }
            }

            if (detectedTypeKey && typeMapping[detectedTypeKey]) {
                // Show detected type info
                const detectedType = typeMapping[detectedTypeKey];
                detectedTypeName.textContent = detectedType.name;
                detectedTypeInfo.style.display = 'block';
                typeSelectionContainer.style.display = 'none';
                
                // Set hidden input or select value
                typeSelect.value = detectedTypeKey;
                
                // Highlight corresponding row in table
                highlightTableRow(detectedTypeKey);
            } else {
                // Show manual selection
                detectedTypeInfo.style.display = 'none';
                typeSelectionContainer.style.display = 'block';
                typeSelect.required = true;
                
                // Remove any highlights
                removeTableRowHighlight();
            }
        }

        // Function to show the selected file
        function showSelectedFile(fileName) {
            fileLabel.textContent = fileName;
            selectedFile.style.opacity = '1';
            uploadArea.classList.add('has-file');
            selectedFile.style.display = 'flex';
            filePlaceholder.style.display = 'none';
        }

        // Function to reset the file input
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
            
            // Reset type detection
            detectedTypeInfo.style.display = 'none';
            typeSelectionContainer.style.display = 'none';
            typeSelect.value = '';
            typeSelect.required = false;
            
            // Remove any highlights
            removeTableRowHighlight();
        }
        
        // Function to highlight table row
        function highlightTableRow(typeKey) {
            // Remove previous highlights
            removeTableRowHighlight();
            
            // Find and highlight the corresponding row
            const row = document.querySelector(`tr[data-type-key="${typeKey}"]`);
            if (row) {
                row.classList.add('table-success', 'highlighted-row');
                
                // Scroll to the row
                setTimeout(() => {
                    row.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 100);
            }
        }
        
        // Function to remove table row highlights
        function removeTableRowHighlight() {
            const highlightedRows = document.querySelectorAll('.type-row.table-success');
            highlightedRows.forEach(row => {
                row.classList.remove('table-success');
            });
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
                detectTypeFromFileName(files[0].name);
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

        selectedFile.addEventListener('mousedown', function(e) {
            if (e.target.closest('.btn-remove-file')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });

        // Form validation
        document.getElementById('upload-form').addEventListener('submit', function(e) {
            const selectedType = typeSelect.value;
            if (!selectedType) {
                e.preventDefault();
                alert('Seleziona una tipologia di import prima di procedere.');
                typeSelectionContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                return false;
            }
        });
    });
</script>
@endpush
