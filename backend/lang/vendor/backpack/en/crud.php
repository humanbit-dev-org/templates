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

    // DATATABLES
    'info' => '_TOTAL_ entries',
    'infoEmpty' => 'No entries',
    'infoFiltered' => '(filtered from _MAX_ total entries)',
    'lengthMenu' => '_MENU_ entries per page',

    // DUPLICATE
    'duplicate' => 'Duplicate',
    'duplicate_confirm' => 'Are you sure you want to duplicate this item?',
    'duplicate_confirmation_title' => 'Item Duplicated',
    'duplicate_confirmation_message' => 'The item has been duplicated successfully.',
    'duplicate_confirmation_not_title' => 'NOT duplicated',
    'duplicate_confirmation_not_message' => "There's been an error. Your item might not have been duplicated.",

    // HINTS
    'hint_hide' => 'Enable this option to hide field ',

    // NAVIGATION
    'open' => 'Open ',
    'relations' => 'Relations',
    'add' => 'Add ',

    // DISCLAIMERS
    'disclaimer_title' => "<i class='las la-info-circle me-1'></i> Informations ",
    'disclaimer_gallery' => 'Provides a preview of the files uploaded to the current media.',
    'disclaimer_hero' => 'Select an option only if the media image is intended for the hero section of a page.',
    'disclaimer_uploads' => 'You can upload multiple media files at the same time.',

    // FILTERS
    'filters' => 'Filters',
    'warning_creating_with_filters' => 'Warning: the new record will have fields pre-filled based on active filters',
    'warning_export_with_filters' => 'Warning: CSV export will include only records matching active filters',

    // SORT
    'sort' => 'Sort',

    // IMPORT AND EXPORT
    'export_import' => 'Export / Import',
    'csv_actions' => 'CSV Actions',
    'csv_export' => 'Export CSV',
    'xlsx_export' => 'Export Excel',
    'csv_import' => 'Import CSV',
    'xlsx_import' => 'Import Excel',
    'csv_import_disabled' => 'CSV import disabled for :section section',
    'csv_export_in_progress' => 'Exporting...',
    'csv_export_timeout' => 'The export is taking longer than expected. Check your downloads or try again later.',

    // BULK OPERATIONS
    'bulk_delete' => 'Delete selected items',
    'bulk_delete_confirm' => 'Are you sure you want to delete all selected items?',
    'bulk_delete_confirmation_title' => 'Items Deleted',
    'bulk_delete_confirmation_message' => 'The selected items have been deleted successfully.',
    'bulk_duplicate' => 'Duplicate selected items',
    'bulk_duplicate_confirm' => 'Are you sure you want to duplicate all selected items?',
    'bulk_duplicate_confirmation_title' => 'Items Duplicated',
    'bulk_duplicate_confirmation_message' => 'The selected items have been duplicated successfully.',
    'bulk_update_field' => 'Select field',
    'bulk_update_value' => 'Select value',
    'bulk_update_apply' => 'Apply',
    'bulk_update_confirm' => 'Apply this value to all selected items?',
    'bulk_update_confirmation_title' => 'Items Updated',
    'bulk_update_confirmation_not_title' => 'Update not completed',
    'bulk_summary_ok' => 'Updated',
    'bulk_summary_skipped' => 'Skipped',
    'bulk_summary_errors' => 'Errors',
    'bulk_action_processed' => 'Bulk operation processed.',
    'bulk_generic_error' => 'Bulk operation failed.',
    'bulk_reason_not_found' => 'Record not found.',
    'bulk_reason_unexpected' => 'Unexpected error.',
    'bulk_reason_fk_constraint' => 'Deletion blocked by a foreign key constraint.',
    'bulk_reason_duplicate_conflict' => 'Unique constraint conflict.',
    'bulk_reason_not_null_violation' => 'A required field cannot be null.',
    'bulk_reason_generic_db' => 'Database constraint error.',
    'bulk_reason_unique_not_nullable' => 'Cannot duplicate: unique field :field is required.',
    'bulk_update_invalid_payload' => 'Bulk update payload is invalid.',
    'bulk_update_field_not_allowed' => 'Selected field is not allowed for bulk update.',
    'bulk_update_value_not_allowed' => 'Selected value is not allowed for this field.',
    'bulk_boolean_quick_actions' => 'Quick actions',
    'bulk_set_field_value' => 'Set :field as :value',
    'bulk_activate_field' => 'Activate :field',
    'bulk_deactivate_field' => 'Deactivate :field',
    'bulk_value_true' => 'True',
    'bulk_value_false' => 'False',
    'selected' => 'items selected',

    // RELATION PREVIEW TOOLTIPS
    'relation_preview_tooltip' => 'View preview',
    'relation_preview_tooltip_no_relation' => 'To view the preview, you need to link an entity :fieldName',

    // FILLABLE FIELDS
    'primary_key_not_editable' => "Primary key not editable (not listed in model's fillable)",
    'field_not_editable' => "Field not editable (not listed in model's fillable)",

    // RELATION COUNTER
    'records' => 'records',
    'view_all_related' => 'View all :count related :relation',
    'view_all_records' => 'View all :count records',
];
