<?php

return [
    // CSV Import general
    'import_csv' => 'Import CSV - :name',
    'upload_file' => 'Carica File',
    'select_file' => 'Seleziona un file',
    'file_requirements' => 'Il file deve essere in formato CSV o XLSX con intestazioni nella prima riga.',
    'analyze_file' => 'Analizza File',
    'cancel' => 'Annulla',
    'drag_drop_or_select' => 'Trascina qui o clicca per selezionare un file',
    'import_instructions_title' => 'Istruzioni',
    'import_instructions_text' => 'Segui questi passaggi per importare correttamente i tuoi dati:',
    'import_instructions_format' => 'Assicurati che il tuo file sia in formato CSV (valori separati da virgola o tabulazioni) o XLSX (Excel).',
    'import_instructions_headers' => 'La prima riga deve contenere le intestazioni delle colonne che saranno mappate ai campi del database.',
    'import_instructions_mapping' => 'Dopo il caricamento, potrai mappare le colonne del file ai campi appropriati del database.',

    // CSV Mapping
    'configure_import' => 'Configura import - :name',
    'column_mapping' => 'Mappatura Colonne',
    'mapping_instructions' => 'Abbina ogni colonna del tuo file a un campo della tabella. Se una colonna non deve essere importata, seleziona "Non importare".',
    'csv_column' => 'Colonna CSV',
    'table_field' => 'Colonna Tabella',
    'do_not_import' => 'Non importare',
    'unique_field' => 'Colonna univoca (se esiste il record verrà aggiornato invece di essere inserito)',
    'no_unique_field' => 'Nessuno - Inserisci sempre nuovi record',
    'matching_fields' => 'Campi di matching (chiave composta)',
    'matching_fields_description' => 'Seleziona uno o più campi per identificare i record esistenti. Tutti i campi selezionati devono corrispondere per aggiornare un record.',
    'matching_fields_mapping_title' => 'Mappatura campi di matching',
    'matching_fields_must_be_mapped' => 'I campi di matching devono essere mappati nel CSV:',
    'select_field' => 'Seleziona un campo...',
    'add_matching_field' => 'Aggiungi campo',
    'start_import' => 'Avvia Import',
    'full_text_unavailable' => 'Testo completo non disponibile',

    // Import Behavior
    'select_import_behavior' => 'Seleziona il comportamento di importazione quando vengono utilizzati campi di matching:',
    'update_and_insert' => 'Aggiorna e Inserisci',

    // User import: password generation
    'generate_passwords_label' => 'Generazione password per gli utenti',
    'generate_passwords_hint' => 'Genera le password di accesso per gli utenti importati (HKDF+Argon2id dallo username). Se disattivato, gli utenti non potranno accedere fino all\'esecuzione di: php artisan auth:rehash-passwords',
    'generate_passwords_warning' => 'Se disattivi la generazione delle password, gli utenti importati non potranno accedere. Potrai generare le password in un secondo momento con il comando: php artisan auth:rehash-passwords',
    'password_empty_badge' => 'Vuota',
    'update_and_insert_description' => 'Aggiorna i record esistenti e inserisci quelli nuovi',
    'update_only' => 'Solo Aggiornamento',
    'update_only_description' => 'Aggiorna solo i record esistenti, ignora quelli nuovi',

    // Import Progress
    'import_in_progress' => 'Import in corso...',
    'processed_rows' => 'Righe elaborate',
    'new_records' => 'Nuovi record',
    'updated_records' => 'Record aggiornati',
    'skipped_rows' => 'Righe saltate',

    // Import Results
    'import_completed' => 'Import completato!',
    'backup_created' => 'È stato creato un backup della tabella in',
    'backup_name' => 'con il nome',
    'back_to_list' => 'Torna alla lista',

    // Import Errors
    'import_error' => 'Errore di Import',
    'error_message' => 'Si è verificato un errore durante il processo di importazione.',

    // New entries
    'exact_match' => 'Corrispondenza esatta',
    'similar_match' => 'Corrispondenza simile',
    'no_match' => 'Nessuna corrispondenza',

    // Required fields
    'required_field' => 'Obbligatorio',
    'required_field_hint' => 'Questo campo è obbligatorio e deve essere impostato.',
    'required_field_hint_update_only' => 'Questo campo è normalmente obbligatorio, ma in modalità Solo Aggiornamento può essere omesso.',
    'matching_required' => 'Matching (Obbligatorio)',
    'matching_field_hint' => 'Questo campo è stato selezionato per il controllo su update/upsert e deve essere impostato.',
    'required_fields_missing' => 'Campi obbligatori mancanti',
    'required_fields_message' => 'I seguenti campi sono obbligatori ma non sono stati mappati con nessuna colonna CSV:',
    'required_fields_tooltip' => 'Seleziona le colonne CSV per tutti i campi obbligatori per continuare',

    // Database constraint badges
    'primary_key' => 'Chiave Primaria',
    'primary_key_hint' => 'Questo campo è la chiave primaria della tabella e identifica univocamente ogni record.',
    'foreign_key' => 'Chiave Esterna',
    'foreign_key_hint' => 'Questo campo è una chiave esterna che referenzia la tabella :table.',
    'unique_constraint' => 'Vincolo Unicità',
    'unique_constraint_hint' => 'Questo campo ha un vincolo di unicità con: :fields. Insieme devono essere unici nel database.',
    'unique_constraint_warning' => 'ATTENZIONE: Questo campo fa parte del vincolo di unicità [:fields]. Se ometti uno o più campi del vincolo come campi di controllo, potresti compromettere integrità e consistenza del database, aggiornando record errati appartenenti a contesti diversi.',

    // Operation log messages
    'operation_log_title' => 'Log Operazioni di Import',
    'row_processing' => 'Elaborazione riga #:row',
    'record_inserted' => 'Nuovo record inserito (:primary_key: :primary_key_value)',
    'record_updated' => 'Record aggiornato (:primary_key: :primary_key_value)',
    'record_skipped' => 'Record saltato',
    'skip_reason_user_not_found' => 'Utente inesistente (user_id non presente in tabella users).',
    'update_only_reason' => "perché è attiva la modalità 'Solo Aggiornamento'",
    'processing_value' => "Elaborazione valore ':value' per il campo ':field'",

    // Info block
    'what_you_need_to_know' => 'Cosa devi sapere',
    'info_csv_format' => 'File CSV o XLSX con colonne delimitate da separatore e intestazioni nella prima riga. Dopo il caricamento sarà possibile mappare le colonne e visualizzare l\'anteprima dell\'import prima di procedere.',
    'info_backup_log' => 'Verrà creato un backup della tabella pre-import e alla fine sarà disponibile un log.',
    'info_rollback' => 'Se qualcosa andrà storto, le operazioni saranno portate alla situazione precedente.',

    // Auto-generated fields
    'auto_generated' => 'Auto-generato',
    'auto_generated_hint' => 'Questo campo viene generato automaticamente se non fornito',

    // Developer section
    'developer_section_title' => 'Sezione Sviluppatore',
    'developer_section_warning' => "Questi campi vengono generati automaticamente dal sistema. Di norma non è necessario modificarli durante l'import. Procedi solo se sai esattamente cosa stai facendo.",
    'enable_developer_fields' => 'Abilita modifica',
    'developer_confirm_enable' => 'Sei sicuro di voler abilitare la modifica dei campi auto-generati? Questi campi vengono normalmente gestiti automaticamente dal sistema.',

    // Auto-generated field hints
    'auto_generated_hint_pk' => 'Chiave primaria auto-incrementale generata automaticamente dal database.',
    'auto_generated_hint_pk_manual' => 'Chiave primaria: deve essere fornita manualmente se si inserisce un nuovo record.',
    'auto_generated_hint_password' => 'La password viene creata automaticamente in fase di salvataggio per i nuovi utenti.',
    'auto_generated_hint_role_id' => "Il ruolo dell'utente è di base User, l'utente con permessi per operare nella piattaforma.",
    'auto_generated_hint_backpack_role_id' => 'Questo campo di default non è impostato.',
];
