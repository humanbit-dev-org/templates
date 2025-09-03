<?php

return [
	//GENERAL
	"getting_started" => "Documentation",
	"getting_started_message" =>
		"If this is your first time using this admin panel, we recommend carefully following the steps below.",
	"complete_task" => "Complete task",
	"completed_task" => "Completed",
	"complete_task_message" => "Are you sure you want to complete the task?",
	"success_task_title" => "Achievement Unlocked 🏆",
	"success_task_message" => "Task successfully completed! You have unlocked: ",

	//ACHIEVEMENTS
	"achievement_1" => "First Steps",
	"achievement_1_gif" => "../static/images/steps.gif",

	"achievement_2" => "Solid Foundations",
	"achievement_2_gif" => "../static/images/smart.gif",

	"achievement_3" => "Practice Makes Perfect - I",
	"achievement_4" => "Practice Makes Perfect - II",
	"achievement_5" => "Practice Makes Perfect - III",
	"achievement_6" => "Unstoppable",
	"achievement_3-5_gif" => "../static/images/muscle.gif",
	"achievement_6_gif" => "../static/images/muscle_robot.gif",
	"achievement_7" => "Graduated with Honors",
	"achievement_7_gif" => "../static/images/graded.gif",

	//STEP 1
	"step_1" => "Explore the left sidebar menu and the interface",
	"step_1_1" =>
		"At the top right, there is a button to change the language. At the top left, there is a dropdown menu with access to your profile and the logout button. Further down, the left sidebar is divided into three main groups:",
	"step_1_2" => "contains sections for managing the main content of the site.",
	"step_1_3" => "contains sections for managing secondary content of the site.",
	"step_1_4" => "contains administration sections.",
	"step_1_disclaimer" =>
		"* menu items and the fields of the related tables, unlike the rest of the admin panel, are not translated and remain in English, as they are extracted directly from the database.",

	//STEP 2
	"step_2" => "Learn the basics",
	"step_2_1" =>
		'The main content is found in the sections <strong class="text-primary">Chapters</strong>, <strong class="text-primary">Thoughts Milano & Home</strong>, <strong class="text-primary">Presidents</strong>, and <strong class="text-primary">Institutionals</strong>. You can link each of these contents to <strong class="text-primary">Media</strong> and <strong class="text-primary">Paragraphs</strong>, which can then be linked to additional <strong class="text-primary">Media</strong> and <strong class="text-primary">Notes</strong>.',
	"step_2_2" =>
		'In the <strong class="text-primary">Media</strong> section you can add multimedia content, which you will later link to other content.',
	"step_2_3" =>
		'In the <strong class="text-primary">SEO</strong> section you can enter search engine optimization information and select the relevant page.',
	"step_2_4" =>
		'In the <strong class="text-primary">Translates</strong> section you can add custom text labels for various parts of the site, with the option to select the relevant page.',
	"step_2_5" =>
		'In the <strong class="text-primary">Pages</strong> section you can add custom descriptions and assign <strong class="text-primary">Media</strong>. This allows you to fully customize the hero section of the pages.',
	"step_2_6" => 'To manage users (add, edit, or delete), use the <strong class="text-primary">Users</strong> section.',
	"dangerous_zone" => "Dangerous zone",

	//STEP 3
	"step_3" => "Manage your first content - Chapters, for the La nostra storia page",
	"step_3_1" => "Here’s the most efficient way to create content for the <strong>La nostra storia</strong> page:",
	"step_3_2" =>
		'Go to the <strong class="text-primary">Chapters</strong> section in a new tab (so you can follow this guide at the same time) and click the <strong>Add Chapter</strong> button in the top right. Fill in the required fields, and before saving, ensure that the save button option is set to <strong>Save and edit this item</strong>. If not, select it. At the bottom, a <strong>Associations</strong> tab will appear, where you can easily link your new chapter to <strong class="text-primary">Paragraphs</strong> and <strong class="text-primary">Media</strong>.',
	"step_3_3" =>
		'To add a cover image for the newly created chapter, click the <strong class="text-primary">Add Media</strong> button in the <strong>Associations</strong> tab. Enter the media title and select the <strong>Uploads</strong> tab, which is dedicated to uploading multimedia files. Upload the image, and before saving, make sure the save button option is set to <strong>Save and go back</strong>. If not, select it.',
	"step_3_4" =>
		'To add a paragraph, click the <strong class="text-primary">Add Paragraph</strong> button in the <strong>Associations</strong> tab. Fill in the required fields, following the hint in the <strong><code>Body formatted</code></strong> field to insert notes. Before saving, ensure the save button option is set to <strong>Save and edit this item</strong>. If not, select it.',
	"step_3_5" =>
		'To add a note, click the <strong class="text-primary">Add Notes</strong> button in the <strong>Associations</strong> tab. Fill in the required fields, and before saving, make sure the save button option is set to <strong>Save and go back</strong>. If not, select it.',
	"step_3_5_1" =>
		'* notes created in the <strong class="text-primary">Notes</strong> section will be linked to those inserted in the <strong><code>Body formatted</code></strong> field of the corresponding <strong class="text-primary">Paragraphs</strong> based on the order in which they are placed in the text, not the number assigned.',
	"step_3_6" =>
		'To insert an image after the paragraph, click the <strong class="text-primary">Add Media</strong> button in the <strong>Associations</strong> tab. For layout and caption management, use the <strong>Paragraphs</strong> and <strong>Caption</strong> tabs, filling in the fields as needed. Once finished, proceed with saving.',
	"step_3_7" =>
		'* this is the fastest way to insert all content types related to <strong class="text-primary">Chapters</strong>. However, it is always possible to add content without navigating through associations, linking them step by step using the dedicated relational fields.',

	//STEP 4
	"step_4" => "Manage Your First Contents - Thoughts Milano & Home, for the Riflessioni su Milano and Homepage pages",
	"step_4_1" =>
		"You can use the same procedure described in Step 3 to create content for the <strong>Riflessioni su Milano</strong> page and the <strong>Homepage</strong>. The only difference is the <strong><code>Page</code></strong> field, where you can select the page in which to insert the entry.",

	//STEP 5
	"step_5" => "Manage Your First Contents - Presidents, for the La voce dei presidenti page",
	"step_5_1" =>
		'You can use the same procedure described in Step 3 to create content for the <strong>La voce dei presidenti</strong> page. The only difference is the president\'s image, which must be uploaded directly in the <strong class="text-primary">Presidents</strong> content. The procedure for uploading the cover image remains unchanged.',

	//STEP 6
	"step_6" => "Manage Your First Contents - Institutionals, for the Assolombarda si racconta page",
	"step_6_1" =>
		'You can use the same procedure described in Step 3 to create content for the <strong>Assolombarda si racconta</strong> page. You will need to create a single <strong class="text-primary">Institutionals</strong> content item, to which you can link all the necessary <strong class="text-primary">Paragraphs</strong>, <strong class="text-primary">Media</strong>, and <strong class="text-primary">Notes</strong>.',

	//STEP 7
	"step_7" => "Manage Lists: Filters, Sorting, Duplicate, Pagination, and Display Methods",
	"step_7_1" =>
		"On the list page, where tables containing all the contents of the current section are displayed, there are some buttons, <strong>Filters</strong> and <strong>Sort</strong>:",
	"step_7_2" =>
		'<strong>Filters</strong><code style="background:none;">-></code>you can filter lists using both standard and relational fields. This allows you to display only the contents that meet your needs. When filters are active, the add content button is modified, pre-setting those fields to the selected values, making it easier to insert multiple specific contents in sequence.',
	"step_7_3" =>
		'<strong>Sort</strong><code style="background:none;">-></code>contents are displayed in their respective pages in ascending order based on the <code>Order</code> field. This button allows you to sort contents directly from the list in a simple and fast way. By default, the <code>Order</code> value is incremented by 1 each time a content is added, unless modified during insertion.',
	"step_7_3_1" =>
		'* the best method for sorting large lists, such as <strong class="text-primary">Paragraphs</strong>, is to first filter based on the relation to the main content and then sort the contents.',
	"step_7_4" =>
		'<strong>Duplicate</strong><code style="background:none;">-></code>you can easily duplicate a content by clicking on the <i class="text-primary la la-clone" style="font-size: 1.5rem;"></i> icon next to the trash bin.',
	"step_7_5" =>
		'<strong>Pagination and Display Methods</strong><code style="background:none;">-></code>at the bottom left, you can select the number of contents displayed per page. At the bottom right, you will find the page navigation. You can change the sorting order by clicking on the visible columns, either in ascending or descending order.',
];
