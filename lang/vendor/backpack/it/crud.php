<?php

return [
	/*
    |--------------------------------------------------------------------------
    | Backpack Crud Language Lines
    |--------------------------------------------------------------------------
    |
    | The following language lines are used by the CRUD interface.
    | You are free to change them to anything
    | you want to customize your views to better match your application.
    |
    */

	// Forms
	"save_action_save_and_new" => "Salva ed aggiungi un nuovo elemento",
	"save_action_save_and_edit" => "Salva e modifica questo elemento",
	"save_action_save_and_back" => "Salva e torna indietro",
	"save_action_save_and_preview" => 'Salva e vai all\'anteprima',
	"save_action_changed_notification" => "Azione predefinita cambiata",

	// Create form
	"add" => "Aggiungi",
	"back_to_all" => "Torna alla lista di ",
	"cancel" => "Annulla",
	"add_a_new" => "Aggiungi nuovo/a ",

	// Edit form
	"edit" => "Modifica",
	"save" => "Salva",

	// Translatable models
	"edit_translations" => "Modifica traduzioni",
	"language" => "Lingua",

	// CRUD table view
	"all" => "Tutti i ",
	"in_the_database" => "nel database",
	"list" => "Lista",
	"reset" => "Reimposta",
	"actions" => "Azioni",
	"preview" => "Anteprima",
	"delete" => "Elimina",
	"admin" => "Amministrazione",
	"details_row" => "Questa è la riga dei dettagli. Modificala a tuo piacimento.",
	"details_row_loading_error" => "C'è stato un errore caricando i dettagli. Riprova.",
	"clone" => "Duplica",
	"clone_success" =>
		"<strong>Elemento duplicato</strong><br>Un nuovo elemento è stato creato con le stesse informazioni di questo.",
	"clone_failure" =>
		"<strong>Duplicazione fallita</strong><br>Il nuovo elemento non può essere creato. Per favore, riprova.",

	// Confirmation messages and bubbles
	"delete_confirm" => "Sei sicuro di eliminare questo elemento?",
	"delete_confirmation_title" => "Elemento eliminato",
	"delete_confirmation_message" => "L'elemento è stato eliminato con successo.",
	"delete_confirmation_not_title" => "NON eliminato",
	"delete_confirmation_not_message" => "C'è stato un errore. L'elemento potrebbe non essere stato eliminato.",
	"delete_confirmation_not_deleted_title" => "Non eliminato",
	"delete_confirmation_not_deleted_message" => "Non è successo niente. L'elemento è al sicuro.",

	// Bulk actions
	"bulk_no_entries_selected_title" => "Nessun record selezionato",
	"bulk_no_entries_selected_message" => 'Seleziona uno o più record su cui effettuare l\'operazione.',

	// Bulk delete
	"bulk_delete_are_you_sure" => "Sei sicuro di voler eliminare :number record?",
	"bulk_delete_sucess_title" => "Record eliminati",
	"bulk_delete_sucess_message" => " record sono stati eliminati",
	"bulk_delete_error_title" => "Record non eliminati",
	"bulk_delete_error_message" => "Non è stato possibile eliminare uno o più record",

	// Bulk clone
	"bulk_clone_are_you_sure" => "Sei sicuro di voler clonare :number record?",
	"bulk_clone_sucess_title" => "Record clonati",
	"bulk_clone_sucess_message" => " record sono stati clonati.",
	"bulk_clone_error_title" => "Record non clonati",
	"bulk_clone_error_message" => "Non è stato possibile clonare uno o più record. Per favore, riprova.",

	// Ajax errors
	"ajax_error_title" => "Errore",
	"ajax_error_text" => "Errore durante il caricamento della pagina. Per favore ricarica la pagina.",

	// DataTables translation
	"emptyTable" => "Nessun record da visualizzare",
	"info" => "_TOTAL_ record",
	"infoEmpty" => "Nessun elemento",
	"infoFiltered" => "(filtrati da _MAX_ record totali)",
	"infoPostFix" => ".",
	"thousands" => ".",
	"lengthMenu" => "_MENU_ record per pagina",
	"loadingRecords" => "Caricamento...",
	"processing" => "Elaborazione...",
	"search" => "Cerca",
	"zeroRecords" => "Nessun record corrispondente",
	"paginate" => [
		"first" => "Primo",
		"last" => "Ultimo",
		"next" => "Prossimo",
		"previous" => "Precedente",
	],
	"aria" => [
		"sortAscending" => ": attiva per ordinare la colonna ascendentemente",
		"sortDescending" => ": attiva per ordinare la colonna discendentemente",
	],
	"export" => [
		"export" => "Esporta",
		"copy" => "Copia",
		"excel" => "Excel",
		"csv" => "CSV",
		"pdf" => "PDF",
		"print" => "Stampa",
		"column_visibility" => "Visibilità colonne",
	],

	// global crud - errors
	"unauthorized_access" => "Accesso non autorizzato - non hai i permessi necessari per vedere questa pagina.",
	"please_fix" => "Per favore correggi i seguenti errori:",

	// global crud - success / error notification bubbles
	"insert_success" => "L'elemento è stato aggiunto correttamente.",
	"update_success" => "L'elemento è stato aggiornato correttamente.",

	// CRUD reorder view
	"reorder" => "Riordina",
	"reorder_text" => "Seleziona e trascina per riordinare.",
	"reorder_success_title" => "Fatto",
	"reorder_success_message" => "Il tuo ordinamento è stato salvato.",
	"reorder_error_title" => "Errore",
	"reorder_error_message" => "Il tuo ordinamento non è stato salvato.",

	// CRUD yes/no
	"yes" => "Sì",
	"no" => "No",

	// CRUD filters navbar view
	"filters" => "Filtri",
	"toggle_filters" => "Attiva/disattiva filtri",
	"remove_filters" => "Rimuovi filtri",
	"apply" => "Applica",

	//filters language strings
	"today" => "Oggi",
	"yesterday" => "Domani",
	"last_7_days" => "Ultimi 7 giorni",
	"last_30_days" => "Ultimi 30 giorni",
	"this_month" => "Questo mese",
	"last_month" => "Mese precedente",
	"custom_range" => "Intervallo di date",
	"weekLabel" => "W",

	// Fields
	"browse_uploads" => "Sfoglia file caricati",
	"select_all" => "Seleziona tutti",
	"select_files" => "Seleziona i files",
	"select_file" => "Seleziona un file",
	"clear" => "Pulisci",
	"page_link" => "Link Pagina",
	"page_link_placeholder" => "http://esempio.com/pagina-desiderata",
	"internal_link" => "Link Interno",
	"internal_link_placeholder" => 'Slug interno. Es: \'admin/page\' (no quotes) for \':url\'',
	"external_link" => "Link Esterno",
	"choose_file" => "Scegli file",
	"new_item" => "Nuovo elemento",
	"select_entry" => "Seleziona un elemento",
	"select_entries" => "Select degli elementi",

	//Table field
	"table_cant_add" => "Impossibile aggiungere una nuova :entity",
	"table_max_reached" => "Numero massimo di :max raggiunto",

	// File manager
	"file_manager" => "File Manager",

	// InlineCreateOperation
	"related_entry_created_success" => 'L\'elemento correlato è stato creato e selezionato.',
	"related_entry_created_error" => "Non è possibile creare elementi correlati.",

	// returned when no translations found in select inputs
	"empty_translations" => "(nessuna voce)",

	// The pivot selector required validation message
	"pivot_selector_required_validation_message" => "Il campo pivot è obbligatorio.",

	//HINTS
	"hint_add_note" =>
		"Per aggiungere una nota, clicca l'icona <i class='note-icon-superscript mx-1' style='font-size:1rem;' title='Superscript'></i>, inserisci il numero e clicca nuovamente l'icona.",
	"hint_hide" => "Abilita questa opzione per nascondere il campo ",
	"hint_bb" =>
		"Questa opzione consente di inserire uno sfondo a 100% di colore blu. Abilitando questa opzione, la caption perderà il colore verde e il testo sarà bianco.",
	"hint_caption_layout" => "Consente di posizionare la caption sopra o sotto.",
	"hint_thought_page" => "Scegli la pagina dove verrà visualizzato l'intervento.",
	"hint_paragraph_layout" =>
		"Le opzioni <span class='text-primary fw-bold'>BLU</span> riguardano i paragrafi contenuti nei <span class='text-primary fw-bold'>Chapters</span>, <span class='text-primary fw-bold'>Presidents</span> e <span class='text-primary fw-bold'>Institutionals</span>. <br> Le opzioni <span class='text-success fw-bold'>VERDI</span> riguardano i paragrafi contenuti in <span class='text-success fw-bold'>Thoughts Milano & Home</span>.",
	"hint_president_image" => "Carica la foto del presidente",
	"hint_mp3" =>
		"⚠️ L'audio può essere caricato solo nei paragrafi in cui il titolo non è nascosto (campo <strong><code>Hide title</strong></code> disattivato).",

	//PARAGRAPHS LAYOUT
	"paragraph_layout_title" => "Layout immagine",
	"photo_left_overlapping" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/photo_left_overlapping.png'>Foto tutta a sinistra, didascalia a destra della foto, parzialmente sovrapposta.</span>",
	"full_width" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/full_width.png'>Immagine a tutta larghezza, didascalia a destra della foto, completamente sovrapposta.</span>",
	"photo_left_not_overlapping" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/photo_left_not_overlapping.png'>Foto tutta a sinistra, didascalia a destra della foto, non sovrapposta.</span>",
	"vertical_left" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/vertical_left.png'>Foto verticale allineata a sinistra del testo, didascalia a destra della foto, parzialmente sovrapposta.</span>",
	"vertical_middle" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/vertical_middle.png'>Foto verticale parzialmente a sinistra, didascalia a destra della foto, non sovrapposta.</span> <hr class='my-3'/>",
	"vertical" =>
		"<span class='text-success fw-bold radio_layout' role='button' data-border='success' data-img='" .
		config("app.url") .
		"/static/images/layouts/vertical.png'>Foto verticale allineata al testo, didascalia a destra della foto, completamente sovrapposta.</span>",
	"horizontal" =>
		"<span class='text-success fw-bold radio_layout' role='button' data-border='success' data-img='" .
		config("app.url") .
		"/static/images/layouts/horizontal.png'>Foto orizzontale tutta a sinistra, didascalia a destra della foto, non sovrapposta.</span>",

	//NAVIGATION
	"open" => "Apri ",
	"associations" => "Associazioni",
	"add" => "Aggiungi ",

	//DISCLAIMERS
	"disclaimer_title" => "<i class='las la-info-circle me-1'></i> Informazione ",
	"disclaimer_paragraph" =>
		"Gestisci le opzioni di layout per le immagini dei paragrafi. <br>La scelta del layout è obbligatoria, altrimenti l'immagine non appare. Questi layout valgono solo per i paragrafi e non per gli altri contenuti.",
	"disclaimer_caption" =>
		"Gestisci il testo della didascalia per le immagini nei paragrafi, nelle hero e nel background delle copertine. <br> Le opzioni di layout sono disponibili solo per le didascalie delle immagini nei paragrafi.",
	"disclaimer_gallery" => "Fornisce una preview dei file caricati sul media corrente.",
	"disclaimer_thought" =>
		"Seleziona un'opzione solo se l'immagine del media è destinata all'autore di un Thought (Riflessione su Milano o Intervento Home).",
	"disclaimer_chapter" =>
		"Seleziona un'opzione solo se l'immagine del media è destinata al background della copertina di un Chapter (La nostra storia).",
	"disclaimer_president" =>
		"Seleziona un'opzione solo se l'immagine del media è destinata al background della copertina di un President (La voce dei presidenti). <br> Per caricare la foto di un presidente, invece, usa la scheda dedicata nella sezione <a href='../president' class='text-primary fw-bold'>President.</a>",
	"disclaimer_hero" => "Seleziona un'opzione solo se l'immagine del media è destinata alla hero di una delle pagine",
	"disclaimer_uploads" => "Puoi caricare più file multimediali contemporaneamente.",

	// CUSTOM

	"filters" => "Filtri",
	"sort" => "Ordina",
	"export_csv" => "Esporta CSV",
	"duplicate" => "Duplica",
	"duplicate_confirm" => "Sei sicuro di voler duplicare questo elemento?",
	"duplicate_confirmation_title" => "Elemento duplicato",
	"duplicate_confirmation_message" => "L'elemento è stato duplicato con successo.",
	"duplicate_confirmation_not_title" => "NON duplicato",
	"duplicate_confirmation_not_message" => "C'è stato un errore. L'elemento potrebbe non essere stato duplicato.",

	"contact_accept_confirm" => "Sei sicuro di voler accettare questa richiesta?",
	"contact_reject_confirm" => "Sei sicuro di voler rifiutare questa richiesta?",
	"contact_reject_reason_required" => "Il motivo del rifiuto è obbligatorio",
	"contact_reject_reason_placeholder" => "Scrivi il motivo del rifiuto",
	"contact_accept" => "Accetta richiesta",
	"contact_reject" => "Rifiuta richiesta",
	"contact_accept_confirmation_title" => "Richiesta accettata",
	"contact_accept_confirmation_message" => "La richiesta è stata accettata con successo.",
	"contact_reject_confirmation_title" => "Richiesta rifiutata",
	"contact_reject_confirmation_message" => "La richiesta è stata rifiutata con successo.",
	"contact_accept_confirmation_not_title" => "NON accettata",
	"contact_reject_confirmation_not_title" => "NON rifiutata",
	"contact_accept_confirmation_not_message" => "C'è stato un errore. La richiesta potrebbe non essere stata accettata.",
	"contact_reject_confirmation_not_message" => "C'è stato un errore. La richiesta potrebbe non essere stata rifiutata.",
];
