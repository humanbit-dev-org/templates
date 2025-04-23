<?php

return [
	// CSV Import general
	"import_csv" => "CSV Import - :name",
	"upload_file" => "Upload CSV File",
	"select_file" => "Select a CSV file",
	"file_requirements" => "The file must be in CSV format with headers in the first row.",
	"analyze_file" => "Analyze File",
	"cancel" => "Cancel",
	"drag_drop_or_select" => "Drag and drop here or click to select a file",
	"import_instructions_title" => "Instructions",
	"import_instructions_text" => "Follow these steps to correctly import your data:",
	"import_instructions_format" => "Make sure your file is in CSV format (comma-separated or tab-separated values).",
	"import_instructions_headers" => "The first row should contain column headers that will be mapped to database fields.",
	"import_instructions_mapping" => "After uploading, you'll be able to map CSV columns to appropriate database fields.",

	// CSV Mapping
	"configure_import" => "Configure CSV Import - :name",
	"column_mapping" => "Column Mapping",
	"mapping_title" => "Column Mapping Settings",
	"mapping_instructions" =>
		'Match each column in your CSV file to a table field. If a column should not be imported, select "Do not import".',
	"csv_column" => "CSV Column",
	"table_field" => "Table Column",
	"do_not_import" => "Do not import",
	"unique_field" => "Unique column (if exists the record will be updated instead of being inserted)",
	"no_unique_field" => "None - Always insert new records",
	"start_import" => "Start Import",
	"csv_preview" => "CSV Preview",
	"csv_preview_note" =>
		"Note: The preview shows only the first 5 rows and is meant to ensure you selected the correct file.",
	"no_preview_data" => "No preview data available",
	"auto_map" => "Auto Mapping",
	"mapping_in_progress" => "Automatic mapping in progress...",
	"full_text_unavailable" => "Full text unavailable",

	// Import Behavior
	"select_import_behavior" => "Select import behavior when a unique field is used:",
	"update_and_insert" => "Update and Insert",
	"update_and_insert_description" => "Update existing records and insert new ones",
	"update_only" => "Update Only",
	"update_only_description" => "Only update existing records, skip new ones",

	// Import Progress
	"import_in_progress" => "Import in progress...",
	"processed_rows" => "Processed rows",
	"new_records" => "New records",
	"updated_records" => "Updated records",
	"skipped_rows" => "Skipped rows",

	// Import Results
	"import_completed" => "Import completed!",
	"backup_created" => "A backup of the table was created at",
	"backup_name" => "with the name",
	"back_to_list" => "Back to list",

	// Import Errors
	"import_error" => "Import Error",
	"error_message" => "An error occurred during the import process.",

	// New entries
	"exact_match" => "Exact match",
	"similar_match" => "Similar match",
	"no_match" => "No match found",

	// Required fields
	"required_field" => "Required",
	"required_fields_missing" => "Required fields missing",
	"required_fields_message" => "The following required fields are not mapped to any CSV column:",
	"required_fields_tooltip" => "Select CSV columns for all required fields to continue",
];
