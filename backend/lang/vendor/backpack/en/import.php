<?php

return [
    // CSV Import general
    'import_csv' => 'CSV Import - :name',
    'upload_file' => 'Upload CSV File',
    'select_file' => 'Select a CSV file',
    'file_requirements' => 'The file must be in CSV format with headers in the first row.',
    'analyze_file' => 'Analyze File',
    'cancel' => 'Cancel',
    'drag_drop_or_select' => 'Drag and drop here or click to select a file',
    'import_instructions_title' => 'Instructions',
    'import_instructions_text' => 'Follow these steps to correctly import your data:',
    'import_instructions_format' => 'Make sure your file is in CSV format (comma-separated or tab-separated values).',
    'import_instructions_headers' => 'The first row should contain column headers that will be mapped to database fields.',
    'import_instructions_mapping' => "After uploading, you'll be able to map CSV columns to appropriate database fields.",

    // CSV Mapping
    'configure_import' => 'Configure CSV Import - :name',
    'column_mapping' => 'Column Mapping',
    'mapping_title' => 'Column Mapping Settings',
    'mapping_instructions' => 'Match each column in your CSV file to a table field. If a column should not be imported, select "Do not import".',
    'csv_column' => 'CSV Column',
    'table_field' => 'Table Column',
    'do_not_import' => 'Do not import',
    'unique_field' => 'Unique column (if exists the record will be updated instead of being inserted)',
    'no_unique_field' => 'None - Always insert new records',
    'matching_fields' => 'Matching fields (composite key)',
    'matching_fields_description' => 'Select one or more fields to identify existing records. All selected fields must match to update a record.',
    'matching_fields_mapping_title' => 'Matching fields mapping',
    'matching_fields_must_be_mapped' => 'Matching fields must be mapped in CSV:',
    'select_field' => 'Select a field...',
    'add_matching_field' => 'Add field',
    'start_import' => 'Start Import',
    'full_text_unavailable' => 'Full text unavailable',

    // Import Behavior
    'select_import_behavior' => 'Select import behavior when matching fields are used:',
    'update_and_insert' => 'Update and Insert',

    // User import: password generation
    'generate_passwords_label' => 'Password generation for users',
    'generate_passwords_hint' => 'Generate login passwords for imported users (HKDF+Argon2id from NDG and ABI). If disabled, users will not be able to log in until you run: php artisan auth:rehash-passwords',
    'generate_passwords_warning' => 'If you disable password generation, imported users will not be able to log in. You can generate passwords later by running the command: php artisan auth:rehash-passwords',
    'password_empty_badge' => 'Empty',
    'update_and_insert_description' => 'Update existing records and insert new ones',
    'update_only' => 'Update Only',
    'update_only_description' => 'Only update existing records, skip new ones',

    // Import Progress
    'import_in_progress' => 'Import in progress...',
    'processed_rows' => 'Processed rows',
    'new_records' => 'New records',
    'updated_records' => 'Updated records',
    'skipped_rows' => 'Skipped rows',

    // Import Results
    'import_completed' => 'Import completed!',
    'backup_created' => 'A backup of the table was created at',
    'backup_name' => 'with the name',
    'back_to_list' => 'Back to list',

    // Import Errors
    'import_error' => 'Import Error',
    'error_message' => 'An error occurred during the import process.',

    // New entries
    'exact_match' => 'Exact match',
    'similar_match' => 'Similar match',
    'no_match' => 'No match found',

    // Required fields
    'required_field' => 'Required',
    'required_field_hint' => 'This field is required and must be set.',
    'required_field_hint_update_only' => 'This field is normally required, but in Update Only mode it can be omitted.',
    'matching_required' => 'Matching (Required)',
    'matching_field_hint' => 'This field has been selected for update/upsert control and must be set.',
    'required_fields_missing' => 'Required fields missing',
    'required_fields_message' => 'The following required fields are not mapped to any CSV column:',
    'required_fields_tooltip' => 'Select CSV columns for all required fields to continue',

    // Database constraint badges
    'primary_key' => 'Primary Key',
    'primary_key_hint' => 'This field is the primary key of the table and uniquely identifies each record.',
    'foreign_key' => 'Foreign Key',
    'foreign_key_hint' => 'This field is a foreign key referencing the :table table.',
    'unique_constraint' => 'Unique Constraint',
    'unique_constraint_hint' => 'This field has a unique constraint with: :fields. Together they must be unique in the database.',
    'unique_constraint_warning' => 'WARNING: This field is part of the unique constraint [:fields]. If you omit one or more constraint fields as matching fields, you may compromise database integrity and consistency by updating incorrect records belonging to different contexts.',

    // Operation log messages
    'operation_log_title' => 'Import Operations Log',
    'row_processing' => 'Processing row #:row',
    'record_inserted' => 'New record inserted (ID: :id)',
    'record_updated' => 'Record updated (ID: :id)',
    'record_skipped' => 'Record skipped',
    'skip_reason_user_not_found' => 'User does not exist (user_id not found in users table).',
    'update_only_reason' => "because 'Update Only' mode is active",
    'processing_value' => "Processing value ':value' for field ':field'",

    // Info block
    'what_you_need_to_know' => 'What you need to know',
    'info_csv_format' => 'Only CSV format with column separators and headers in the first row is accepted.',
    'info_backup_log' => 'A backup of the table will be created before import and a log will be available at the end.',
    'info_rollback' => 'If something goes wrong, operations will be rolled back to the previous state.',

    // Auto-generated fields
    'auto_generated' => 'Auto-generated',
    'auto_generated_hint' => 'This field is auto-generated if not provided',

    // Developer section
    'developer_section_title' => 'Developer Section',
    'developer_section_warning' => 'These fields are auto-generated by the system. Normally you should not modify them during import. Proceed only if you know exactly what you are doing.',
    'enable_developer_fields' => 'Enable editing',
    'developer_confirm_enable' => 'Are you sure you want to enable editing of auto-generated fields? These fields are normally managed automatically by the system.',

    // Auto-generated field hints
    'auto_generated_hint_pk' => 'Auto-increment primary key generated automatically by the database.',
    'auto_generated_hint_pk_manual' => 'Primary key: must be provided manually when inserting a new record.',
    'auto_generated_hint_user_id' => 'User ID is auto-generated in the format {banca_abi}-{ndg}.',
    'auto_generated_hint_email' => 'Email is auto-generated in the format {banca_abi}.{ndg}@cashberry.it',
    'auto_generated_hint_password' => 'Password is automatically created when saving new users.',
    'auto_generated_hint_role_id' => 'User role defaults to User, the role with permissions to operate in the platform.',
    'auto_generated_hint_backpack_role_id' => 'This field is not set by default.',
];
