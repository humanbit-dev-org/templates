@php
// Lista delle rotte dove disabilitare l'importazione
$disableImportFor = ['page'];

// Estrai il nome della rotta corrente dal percorso
$currentRoute = str_replace(config('backpack.base.route_prefix', 'admin').'/', '', $crud->route);

// Verifica se l'importazione deve essere disabilitata per questa rotta
$importDisabled = in_array($currentRoute, $disableImportFor);
@endphp

<div class="csv-actions-container">
    <button type="button" id="csvActionsButton" class="btn btn-primary" onclick="toggleCsvPopup()">
        <i class="la la-file-csv"></i> CSV
    </button>

    <!-- Popup custom anziché modal -->
    <div id="csvActionsPopup" class="csv-popup">
        <div class="csv-popup-content">
            <div class="csv-popup-header">
                <h5>Azioni CSV</h5>
                <button type="button" class="csv-popup-close" onclick="toggleCsvPopup()">
                    <span>&times;</span>
                </button>
            </div>
            <div class="csv-popup-body">
                <div class="csv-buttons">
                    <a href="{{ url($crud->route.'/export-csv') }}" class="btn btn-primary btn-sm">
                        <i class="la la-file-export me-1"></i> Esporta
                    </a>

                    @if($importDisabled)
                    <span
                        data-toggle="tooltip"
                        data-bs-toggle="tooltip"
                        data-placement="top"
                        data-bs-placement="top"
                        title="L'importazione CSV non è disponibile per la sezione {{ ucfirst($currentRoute) }}">
                        <button class="btn btn-secondary btn-sm disabled-import-btn"
                            disabled
                            title="L'importazione CSV non è disponibile per la sezione {{ ucfirst($currentRoute) }}">
                            <i class="la la-file-upload me-1"></i> Importa
                        </button>
                    </span>
                    @else
                    <a href="{{ url($crud->route.'/import-csv') }}" class="btn btn-success btn-sm">
                        <i class="la la-file-upload me-1"></i> Importa
                    </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    function toggleCsvPopup() {
        var popup = document.getElementById('csvActionsPopup');

        if (popup.classList.contains('visible')) {
            // Avvia l'animazione di chiusura
            popup.classList.add('animate-out');
            popup.classList.remove('animate-in');

            // Rimuovi la classe 'visible' dopo che l'animazione è completata
            setTimeout(function() {
                popup.classList.remove('visible');
                popup.classList.remove('animate-out');
                document.removeEventListener('click', closePopupOnClickOutside);
            }, 200); // Tempo leggermente più breve per evitare lag
        } else {
            // Mostra il popup e avvia l'animazione di apertura
            popup.classList.add('visible');
            popup.classList.add('animate-in');
            popup.classList.remove('animate-out');

            // Aggiungi l'event listener per chiudere quando si clicca fuori
            document.addEventListener('click', closePopupOnClickOutside);
        }
    }

    function closePopupOnClickOutside(event) {
        var popup = document.getElementById('csvActionsPopup');
        var button = document.getElementById('csvActionsButton');

        // Se il click non è sul popup o sul bottone, chiudi il popup
        if (!popup.contains(event.target) && !button.contains(event.target)) {
            // Avvia l'animazione di chiusura
            popup.classList.add('animate-out');
            popup.classList.remove('animate-in');

            // Rimuovi la classe 'visible' dopo che l'animazione è completata
            setTimeout(function() {
                popup.classList.remove('visible');
                popup.classList.remove('animate-out');
                document.removeEventListener('click', closePopupOnClickOutside);
            }, 200); // Tempo leggermente più breve per evitare lag
        }
    }

    // Inizializza tooltip per Bootstrap 4 e 5
    document.addEventListener('DOMContentLoaded', function() {
        // Inizializza per Bootstrap 4
        if (typeof $ !== 'undefined' && typeof $.fn !== 'undefined' && typeof $.fn.tooltip === 'function') {
            $('[data-toggle="tooltip"]').tooltip();
        }

        // Inizializza per Bootstrap 5
        if (typeof bootstrap !== 'undefined' && typeof bootstrap.Tooltip !== 'undefined') {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        }
    });

    // Fallback per tooltip con title attribute nativo
    document.addEventListener('DOMContentLoaded', function() {
        var disabledButtons = document.querySelectorAll('.disabled-import-btn');
        disabledButtons.forEach(function(button) {
            button.addEventListener('mouseenter', function() {
                if (!button.getAttribute('data-tooltip-initialized')) {
                    // Se i tooltip Bootstrap non sono inizializzati, usiamo il title nativo
                    button.style.position = 'relative';
                }
            });
        });
    });
</script>