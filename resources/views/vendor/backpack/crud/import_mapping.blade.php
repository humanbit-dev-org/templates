@extends(backpack_view('blank'))

@section('header')
    <section class="container-fluid">
        <h2>
            <span class="text-capitalize">Configura importazione CSV</span>
        </h2>
    </section>
@endsection

@section('content')
    <div class="row">
        <div class="col-md-12">
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title">Mappatura Colonne</h3>
                </div>
                <div class="card-body">
                    <form id="import-mapping-form">
                        @csrf
                        <input type="hidden" name="file_path" value="{{ $filePath }}">
                        <input type="hidden" name="delimiter" value="{{ $delimiter }}">
                        
                        <div class="alert alert-info">
                            <p>Associa ogni colonna del file CSV con un campo della tabella. Se una colonna non dovrebbe essere importata, seleziona "Non importare".</p>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Colonna CSV</th>
                                        <th>Campo della tabella</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($csvHeaders as $index => $header)
                                        <tr>
                                            <td>{{ $header }}</td>
                                            <td>
                                                <select name="column_mapping[{{ $index }}]" class="form-control">
                                                    <option value="">Non importare</option>
                                                    @foreach($tableColumns as $column)
                                                        <option value="{{ $column }}" {{ (isset($columnMapping[$index]) && $columnMapping[$index] == $column) ? 'selected' : '' }}>
                                                            {{ $column }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="form-group">
                            <label for="unique_field">Campo univoco (se esiste verrà aggiornato invece di inserito)</label>
                            <select name="unique_field" id="unique_field" class="form-control">
                                <option value="">Nessuno - Inserisci sempre nuovi record</option>
                                @foreach($tableColumns as $column)
                                    <option value="{{ $column }}">{{ $column }}</option>
                                @endforeach
                            </select>
                            <small class="form-text text-muted">
                                Se selezionato, i record con lo stesso valore in questo campo verranno aggiornati invece di creare duplicati.
                            </small>
                        </div>
                        
                        <div class="form-group">
                            <button type="submit" class="btn btn-primary" id="start-import">Avvia Importazione</button>
                            <a href="{{ url($crud_route) }}" class="btn btn-default">Annulla</a>
                        </div>
                    </form>
                    
                    <div id="import-progress" style="display: none;">
                        <h4>Importazione in corso...</h4>
                        <div class="progress">
                            <div class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                        </div>
                        
                        <div class="mt-3">
                            <span id="progress-text">0%</span>
                            <div id="import-stats">
                                <p>Righe elaborate: <span id="total-rows">0</span></p>
                                <p>Nuovi record: <span id="created-rows">0</span></p>
                                <p>Record aggiornati: <span id="updated-rows">0</span></p>
                                <p>Righe saltate: <span id="skipped-rows">0</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <div id="import-results" style="display: none;">
                        <div class="alert alert-success">
                            <h4>Importazione completata!</h4>
                            <div id="result-stats"></div>
                            <p>Un backup della tabella è stato creato in <code>/storage/app/import-backups/</code> con il nome <span id="backup-filename"></span></p>
                        </div>
                    </div>
                    
                    <div id="import-error" style="display: none;">
                        <div class="alert alert-danger">
                            <h4>Errore durante l'importazione</h4>
                            <p id="error-message"></p>
                            <p>Un backup della tabella è stato creato in <code>/storage/app/import-backups/</code> con il nome <span id="error-backup-filename"></span></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@push('after_scripts')
<script>
    $(document).ready(function() {
        $('#import-mapping-form').on('submit', function(e) {
            e.preventDefault();
            
            // Nascondi il form e mostra la barra di avanzamento
            $(this).hide();
            $('#import-progress').show();
            
            // Prepara i dati per l'invio
            var formData = $(this).serialize();
            
            // Funzione per controllare lo stato dell'importazione
            var checkImportStatus = function() {
                $.ajax({
                    url: '{{ url($crud_route."/import-csv/status") }}',
                    type: 'GET',
                    success: function(response) {
                        if (response.status === 'processing') {
                            updateProgressBar(response);
                            setTimeout(checkImportStatus, 1000);
                        } else if (response.status === 'success') {
                            importCompleted(response);
                        } else if (response.status === 'error') {
                            importError(response);
                        }
                    },
                    error: function() {
                        setTimeout(checkImportStatus, 2000);
                    }
                });
            };
            
            // Avvia l'importazione
            $.ajax({
                url: '{{ url($crud_route."/import-csv/process") }}',
                type: 'POST',
                data: formData,
                success: function(response) {
                    if (response.status === 'success') {
                        importCompleted(response);
                    } else if (response.status === 'error') {
                        importError(response);
                    } else {
                        // L'importazione è iniziata, controlla lo stato
                        setTimeout(checkImportStatus, 1000);
                    }
                },
                error: function(xhr) {
                    var response = xhr.responseJSON || {};
                    importError(response);
                }
            });
        });
        
        // Funzione per aggiornare la barra di avanzamento
        function updateProgressBar(data) {
            var progress = data.progress || 0;
            $('.progress-bar').css('width', progress + '%');
            $('#progress-text').text(progress + '%');
            
            $('#total-rows').text(data.total || 0);
            $('#created-rows').text(data.created || 0);
            $('#updated-rows').text(data.updated || 0);
            $('#skipped-rows').text(data.skipped || 0);
        }
        
        // Funzione per mostrare il completamento dell'importazione
        function importCompleted(data) {
            $('#import-progress').hide();
            $('#import-results').show();
            
            var resultHtml = '<p>Righe elaborate: ' + (data.total || 0) + '</p>';
            resultHtml += '<p>Nuovi record: ' + (data.created || 0) + '</p>';
            resultHtml += '<p>Record aggiornati: ' + (data.updated || 0) + '</p>';
            resultHtml += '<p>Righe saltate: ' + (data.skipped || 0) + '</p>';
            
            $('#result-stats').html(resultHtml);
            $('#backup-filename').text(data.backupFile || '');
            
            // Aggiungi un pulsante per tornare alla lista
            $('#import-results').append('<div class="mt-3"><a href="{{ url($crud_route) }}" class="btn btn-success">Torna alla lista</a></div>');
        }
        
        // Funzione per mostrare gli errori
        function importError(data) {
            $('#import-progress').hide();
            $('#import-error').show();
            
            $('#error-message').text(data.message || 'Si è verificato un errore durante l\'importazione.');
            $('#error-backup-filename').text(data.backupFile || '');
            
            // Aggiungi un pulsante per tornare alla lista
            $('#import-error').append('<div class="mt-3"><a href="{{ url($crud_route) }}" class="btn btn-default">Torna alla lista</a></div>');
        }
    });
</script>
@endpush 
