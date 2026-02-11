<?php

return [
	// CSV Import general
	"import_csv" => "Import CSV - :name",
	"upload_file" => "Carica File CSV",
	"select_file" => "Seleziona un file CSV",
	"file_requirements" => "Il file deve essere in formato CSV con intestazioni nella prima riga.",
	"analyze_file" => "Analizza File",
	"cancel" => "Annulla",
	"drag_drop_or_select" => "Trascina qui o clicca per selezionare un file",
	"import_instructions_title" => "Istruzioni",
	"import_instructions_text" => "Segui questi passaggi per importare correttamente i tuoi dati:",
	"import_instructions_format" =>
		"Assicurati che il tuo file sia in formato CSV (valori separati da virgole o tabulazioni).",
	"import_instructions_headers" =>
		"La prima riga deve contenere le intestazioni delle colonne che saranno mappate ai campi del database.",
	"import_instructions_mapping" => "Dopo il caricamento, potrai mappare le colonne CSV ai campi appropriati del database.",

	// CSV Mapping
	"configure_import" => "Configura Import CSV - :name",
	"column_mapping" => "Mappatura Colonne",
	"mapping_instructions" =>
		'Abbina ogni colonna del tuo file CSV a un campo della tabella. Se una colonna non deve essere importata, seleziona "Non importare".',
	"csv_column" => "Colonna CSV",
	"table_field" => "Colonna Tabella",
	"do_not_import" => "Non importare",
	"unique_field" => "Colonna univoca (se esiste il record verrà aggiornato invece di essere inserito)",
	"no_unique_field" => "Nessuno - Inserisci sempre nuovi record",
	"start_import" => "Avvia Import",
	"auto_map" => "Mappatura Automatica",
	"mapping_in_progress" => "Mappatura automatica in corso...",
	"full_text_unavailable" => "Testo completo non disponibile",

	// Import Behavior
	"select_import_behavior" => "Seleziona il comportamento di importazione quando viene utilizzato un campo univoco:",
	"update_and_insert" => "Aggiorna e Inserisci",
	"update_and_insert_description" => "Aggiorna i record esistenti e inserisci quelli nuovi",
	"update_only" => "Solo Aggiornamento",
	"update_only_description" => "Aggiorna solo i record esistenti, ignora quelli nuovi",

	// Import Progress
	"import_in_progress" => "Import in corso...",
	"processed_rows" => "Righe elaborate",
	"new_records" => "Nuovi record",
	"updated_records" => "Record aggiornati",
	"skipped_rows" => "Righe saltate",

	// Import Results
	"import_completed" => "Import completato!",
	"backup_created" => "È stato creato un backup della tabella in",
	"backup_name" => "con il nome",
	"back_to_list" => "Torna alla lista",

	// Import Errors
	"import_error" => "Errore di Import",
	"error_message" => "Si è verificato un errore durante il processo di importazione.",

	// New entries
	"exact_match" => "Corrispondenza esatta",
	"similar_match" => "Corrispondenza simile",
	"no_match" => "Nessuna corrispondenza",

	// Required fields
	"required_field" => "Obbligatorio",
	"required_fields_missing" => "Campi obbligatori mancanti",
	"required_fields_message" => "I seguenti campi sono obbligatori ma non sono stati mappati con nessuna colonna CSV:",
	"required_fields_tooltip" => "Seleziona le colonne CSV per tutti i campi obbligatori per continuare",

	// Operation log messages
	"operation_log_title" => "Log Operazioni di Import",
	"row_processing" => "Elaborazione riga #:row",
	"record_inserted" => "Nuovo record inserito (:primary_key: :primary_key_value)",
	"record_updated" => "Record aggiornato (:primary_key: :primary_key_value)",
	"record_skipped" => "Record saltato",
	"update_only_reason" => "perché è attiva la modalità 'Solo Aggiornamento'",
	"processing_value" => "Elaborazione valore ':value' per il campo ':field'",

	// Info block
	"what_you_need_to_know" => "Cosa devi sapere",
	"info_csv_format" => "Si accetta solo formato CSV con separazioni di colonne e intestazioni nella prima riga.",
	"info_backup_log" => "Verrà creato un backup della tabella pre-import e alla fine sarà disponibile un log.",
	"info_rollback" => "Se qualcosa andrà storto, le operazioni saranno portate alla situazione precedente.",
];
