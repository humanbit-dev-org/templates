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
	"save_action_save_and_new" => "Save and new item",
	"save_action_save_and_edit" => "Save and edit this item",
	"save_action_save_and_back" => "Save and back",
	"save_action_save_and_preview" => "Save and preview",
	"save_action_changed_notification" => "Default behaviour after saving has been changed.",

	// Create form
	"add" => "Add",
	"back_to_all" => "Back to all ",
	"cancel" => "Cancel",
	"add_a_new" => "Add a new ",

	// Edit form
	"edit" => "Edit",
	"save" => "Save",

	// Translatable models
	"edit_translations" => "Translation",
	"language" => "Language",

	// CRUD table view
	"all" => "All ",
	"in_the_database" => "in the database",
	"list" => "List",
	"reset" => "Reset",
	"actions" => "Actions",
	"preview" => "Preview",
	"delete" => "Delete",
	"admin" => "Admin",
	"details_row" => "This is the details row. Modify as you please.",
	"details_row_loading_error" => "There was an error loading the details. Please retry.",
	"clone" => "Clone",
	"clone_success" => "<strong>Entry cloned</strong><br>A new entry has been added, with the same information as this one.",
	"clone_failure" => "<strong>Cloning failed</strong><br>The new entry could not be created. Please try again.",

	// Confirmation messages and bubbles
	"delete_confirm" => "Are you sure you want to delete this item?",
	"delete_confirmation_title" => "Item Deleted",
	"delete_confirmation_message" => "The item has been deleted successfully.",
	"delete_confirmation_not_title" => "NOT deleted",
	"delete_confirmation_not_message" => "There's been an error. Your item might not have been deleted.",
	"delete_confirmation_not_deleted_title" => "Not deleted",
	"delete_confirmation_not_deleted_message" => "Nothing happened. Your item is safe.",

	// Bulk actions
	"bulk_no_entries_selected_title" => "No entries selected",
	"bulk_no_entries_selected_message" => "Please select one or more items to perform a bulk action on them.",

	// Bulk delete
	"bulk_delete_are_you_sure" => "Are you sure you want to delete these :number entries?",
	"bulk_delete_sucess_title" => "Entries deleted",
	"bulk_delete_sucess_message" => " items have been deleted",
	"bulk_delete_error_title" => "Delete failed",
	"bulk_delete_error_message" => "One or more items could not be deleted",

	// Bulk clone
	"bulk_clone_are_you_sure" => "Are you sure you want to clone these :number entries?",
	"bulk_clone_sucess_title" => "Entries cloned",
	"bulk_clone_sucess_message" => " items have been cloned.",
	"bulk_clone_error_title" => "Cloning failed",
	"bulk_clone_error_message" => "One or more entries could not be created. Please try again.",

	// Ajax errors
	"ajax_error_title" => "Error",
	"ajax_error_text" => "Error loading page. Please refresh the page.",

	// DataTables translation
	"emptyTable" => "No data available in table",
	"info" => "_TOTAL_ entries",
	"infoEmpty" => "No entries",
	"infoFiltered" => "(filtered from _MAX_ total entries)",
	"infoPostFix" => ".",
	"thousands" => ",",
	"lengthMenu" => "_MENU_ entries per page",
	"loadingRecords" => "Loading...",
	"processing" => "Processing...",
	"search" => "Search",
	"zeroRecords" => "No matching entries found",
	"paginate" => [
		"first" => "First",
		"last" => "Last",
		"next" => "Next",
		"previous" => "Previous",
	],
	"aria" => [
		"sortAscending" => ": activate to sort column ascending",
		"sortDescending" => ": activate to sort column descending",
	],
	"export" => [
		"export" => "Export",
		"copy" => "Copy",
		"excel" => "Excel",
		"csv" => "CSV",
		"pdf" => "PDF",
		"print" => "Print",
		"column_visibility" => "Column visibility",
	],
	"custom_views" => [
		"title" => "custom views",
		"title_short" => "views",
		"default" => "default",
	],

	// global crud - errors
	"unauthorized_access" => "Unauthorized access - you do not have the necessary permissions to see this page.",
	"please_fix" => "Please fix the following errors:",

	// global crud - success / error notification bubbles
	"insert_success" => "The item has been added successfully.",
	"update_success" => "The item has been modified successfully.",

	// CRUD reorder view
	"reorder" => "Reorder",
	"reorder_text" => "Use drag&drop to reorder.",
	"reorder_success_title" => "Done",
	"reorder_success_message" => "Your order has been saved.",
	"reorder_error_title" => "Error",
	"reorder_error_message" => "Your order has not been saved.",

	// CRUD yes/no
	"yes" => "Yes",
	"no" => "No",

	// CRUD filters navbar view
	"filters" => "Filters",
	"toggle_filters" => "Toggle filters",
	"remove_filters" => "Remove filters",
	"apply" => "Apply",

	//filters language strings
	"today" => "Today",
	"yesterday" => "Yesterday",
	"last_7_days" => "Last 7 Days",
	"last_30_days" => "Last 30 Days",
	"this_month" => "This Month",
	"last_month" => "Last Month",
	"custom_range" => "Custom Range",
	"weekLabel" => "W",

	// Fields
	"browse_uploads" => "Browse uploads",
	"select_all" => "Select All",
	"unselect_all" => "Unselect All",
	"select_files" => "Select files",
	"select_file" => "Select file",
	"clear" => "Clear",
	"page_link" => "Page link",
	"page_link_placeholder" => "http://example.com/your-desired-page",
	"internal_link" => "Internal link",
	"internal_link_placeholder" => 'Internal slug. Ex: \'admin/page\' (no quotes) for \':url\'',
	"external_link" => "External link",
	"choose_file" => "Choose file",
	"new_item" => "New Item",
	"select_entry" => "Select an entry",
	"select_entries" => "Select entries",
	"upload_multiple_files_selected" => "Files selected. After save, they will show up above.",

	//Table field
	"table_cant_add" => "Cannot add new :entity",
	"table_max_reached" => "Maximum number of :max reached",

	// google_map
	"google_map_locate" => "Get my location",

	// File manager
	"file_manager" => "File Manager",

	// InlineCreateOperation
	"related_entry_created_success" => "Related entry has been created and selected.",
	"related_entry_created_error" => "Could not create related entry.",
	"inline_saving" => "Saving...",

	// returned when no translations found in select inputs
	"empty_translations" => "(empty)",

	// The pivot selector required validation message
	"pivot_selector_required_validation_message" => "The pivot field is required.",

	// Quick button messages
	"quick_button_ajax_error_title" => "Request Failed!",
	"quick_button_ajax_error_message" => "There was an error processing your request.",
	"quick_button_ajax_success_title" => "Request Completed!",
	"quick_button_ajax_success_message" => "Your request was completed with success.",

	// translations
	"no_attributes_translated" => "This entry is not translated in :locale.",
	"no_attributes_translated_href_text" => "Fill inputs from :locale",

	//HINTS
	"hint_add_note" =>
		"To add a note, click on the <i class='note-icon-superscript mx-1' style='font-size:1rem;' title='Superscript'></i> icon, enter the number and click the icon again.",
	"hint_hide" => "Enable this option to hide field ",
	"hint_bb" =>
		"This option allows you to insert a 100% blue background color. By enabling this option, the caption will lose the green background, and the text color will be white.",
	"hint_caption_layout" => "Allows caption to be on top or bottom.",
	"hint_thought_page" => "Choose the page where the thought will be displayed",
	"hint_paragraph_layout" =>
		"The <span class='text-primary fw-bold'>BLUE</span> options refer to the paragraphs contained in <span class='text-primary fw-bold'>Chapters</span>, <span class='text-primary fw-bold'>Presidents</span>, and <span class='text-primary fw-bold'>Institutionals</span>. <br> The <span class='text-success fw-bold'>GREEN</span> options refer to the paragraphs contained in <span class='text-success fw-bold'>Thoughts Milano & Home</span>.",
	"hint_president_image" => "Upload the photo of the president",
	"hint_mp3" =>
		"⚠️ Audio can only be uploaded to paragraphs where the title is not hidden (the <strong><code>Hide title</code></strong> field is disabled).",

	// PARAGRAPHS LAYOUT
	"paragraph_layout_title" => "Image Layout",
	"photo_left_overlapping" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-border='--tblr-primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/photo_left_overlapping.png'>Photo fully on the left, caption to the right of the photo, partially overlapping.</span>",
	"full_width" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-border='--tblr-primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/full_width.png'>Full-width image, caption to the right of the photo, fully overlapping.</span>",
	"photo_left_not_overlapping" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-border='--tblr-primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/photo_left_not_overlapping.png'>Photo fully on the left, caption to the right of the photo, not overlapping.</span>",
	"vertical_left" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-border='--tblr-primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/vertical_left.png'>Vertical photo aligned to the left of the text, caption to the right of the photo, partially overlapping.</span>",
	"vertical_middle" =>
		"<span class='text-primary fw-bold radio_layout' role='button' data-border='primary' data-img='" .
		config("app.url") .
		"/static/images/layouts/vertical_middle.png'>Vertical photo partially on the left, caption to the right of the photo, not overlapping.</span> <hr class='my-3'/>",
	"vertical" =>
		"<span class='text-success fw-bold radio_layout' role='button' data-border='success' data-img='" .
		config("app.url") .
		"/static/images/layouts/vertical.png'>Vertical photo aligned with the text, caption to the right of the photo, fully overlapping.</span>",
	"horizontal" =>
		"<span class='text-success fw-bold radio_layout' role='button' data-border='success' data-img='" .
		config("app.url") .
		"/static/images/layouts/horizontal.png'>Horizontal photo fully on the left, caption to the right of the photo, not overlapping.</span>",

	//NAVIGATION
	"open" => "Open ",
	"associations" => "Associations",
	"add" => "Add ",

	// DISCLAIMERS
	"disclaimer_title" => "<i class='las la-exclamation-triangle me-1'></i> Warning ",
	"disclaimer_paragraph" =>
		"Manage the layout options for paragraph images. <br>The layout selection is mandatory; otherwise, the image will not appear. These layouts apply only to paragraphs and not to other content.",
	"disclaimer_caption" =>
		"Manage the caption text for images in paragraphs, heroes, and cover backgrounds. <br> Layout options are only available for image captions in paragraphs.",
	"disclaimer_gallery" => "Provides a preview of the files uploaded to the current media.",
	"disclaimer_thought" =>
		"Select an option only if the media image is intended for the author of a Thought (Riflessioni su Milano or Homepage Contribution).",
	"disclaimer_chapter" =>
		"Select an option only if the media image is intended for the background of a Chapter cover (La nostra storia).",
	"disclaimer_president" =>
		"Select an option only if the media image is intended for the background of a President cover (La voce dei presidenti). <br> To upload a president’s photo, use the dedicated tab in the <a href='../president' class='text-primary fw-bold'>President</a> section instead.",
	"disclaimer_hero" => "Select an option only if the media image is intended for the hero section of a page.",
	"disclaimer_uploads" => "You can upload multiple media files at the same time.",

	// CUSTOM

	"filters" => "Filters",
	"sort" => "Sort",
	"export_csv" => "Export CSV",
	"duplicate" => "Duplicate",
	"duplicate_confirm" => "Are you sure you want to duplicate this item?",
	"duplicate_confirmation_title" => "Item Duplicated",
	"duplicate_confirmation_message" => "The item has been duplicated successfully.",
	"duplicate_confirmation_not_title" => "NOT duplicated",
	"duplicate_confirmation_not_message" => "There's been an error. Your item might not have been duplicated.",

	"contact_accept_confirm" => "Are you sure you want to accept this request?",
	"contact_reject_confirm" => "Are you sure you want to reject this request?",
	"contact_reject_reason_placeholder" => "Write the reason for rejection",
	"contact_reject_reason_required" => "The reason for rejection is required",
	"contact_accept" => "Accept request",
	"contact_reject" => "Reject request",
	"contact_accept_confirmation_title" => "Request accepted",
	"contact_accept_confirmation_message" => "The request has been accepted successfully.",
	"contact_reject_confirmation_title" => "Request rejected",
	"contact_reject_confirmation_message" => "The request has been rejected successfully.",
	"contact_accept_confirmation_not_title" => "NOT accepted",
	"contact_reject_confirmation_not_title" => "NOT rejected",
	"contact_accept_confirmation_not_message" => "There's been an error. Your request might not have been accepted.",
	"contact_reject_confirmation_not_message" => "There's been an error. Your request might not have been rejected.",
];
