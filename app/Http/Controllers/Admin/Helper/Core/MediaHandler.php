<?php

namespace App\Http\Controllers\Admin\Helper\Core;

use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Handles media files across the application
 *
 * Provides functionality for file uploads, media previews,
 * and proper display of different media types (images,
 * videos, audio) in both form and list views.
 */
class MediaHandler
{
	// Array of all file types and their corresponding MIME types
	public static $uploadFields = [
		"image_path" => "image/*",
		"mp4_path" => "video/mp4",
		"ogv_path" => "video/ogg",
		"webm_path" => "video/webm",
		"mp3_path" => "audio/mpeg",
		"ogg_path" => "video/ogg",
		"file_path" => "application/pdf",
	];

	/**
	 * Configures upload fields for media columns
	 *
	 * Sets the field type to "upload" and configures appropriate
	 * options based on the media type (image, video, audio, file)
	 *
	 * @param string $column Column name
	 * @param \Backpack\CRUD\app\Library\CrudPanel\CrudField $field Field to configure
	 * @return bool True if the field was configured as upload field, false otherwise
	 */
	public static function configureUploadField($column, $field)
	{
		foreach (self::$uploadFields as $upload => $mime) {
			if (str_contains($column, $upload)) {
				$field->upload(true);
				$field->label("Upload " . ucfirst(str_replace("_path", "", $upload)));
				$field->disk("uploads");
				$field->attributes(["accept" => $mime]);

				return true;
			}
		}

		return false;
	}

	/**
	 * Adds media previews to the "Gallery" tab (edit mode only)
	 *
	 * Generates HTML preview elements for images, videos and audio files
	 * attached to the current model entry
	 *
	 * @param Model $model Current model
	 */
	public static function addMediaPreviews(Model $model)
	{
		$entryId = CRUD::getCurrentEntryId();
		if (!$entryId) {
			return;
		}

		$entry = $model->find($entryId);
		$previewHtml = "";

		$getCleanFileName = function ($filePath) {
			return str_replace("media/", "", basename($filePath));
		};

		// Generate image preview
		if (!empty($entry->image_path)) {
			$imageUrl = config("app.url") . "/storage/uploads/" . $entry->image_path;
			$fileName = $getCleanFileName($entry->image_path);
			$previewHtml .=
				'<div class="media-item">
                    <strong>' .
				htmlspecialchars($fileName) .
				'</strong>
                    <a href="' .
				$imageUrl .
				'" target="_blank">
                        <img src="' .
				$imageUrl .
				'" alt="Image Preview"/>
                    </a>
                </div>';
		}

		// Generate video previews (MP4, WebM, OGV, OGG)
		foreach (
			["mp4_path" => "mp4", "webm_path" => "webm", "ogv_path" => "ogg", "ogg_path" => "ogg"]
			as $column => $mimeType
		) {
			if (!empty($entry->$column)) {
				$videoUrl = config("app.url") . "/storage/uploads/" . $entry->$column;
				$fileName = $getCleanFileName($entry->$column);

				$previewHtml .=
					'<div class="media-item">
                        <strong>' .
					htmlspecialchars($fileName) .
					'</strong>
                        <video controls>
                            <source src="' .
					$videoUrl .
					'" type="video/' .
					$mimeType .
					'">
                            Your browser does not support the video tag.
                        </video>
                    </div>';
			}
		}

		// Generate audio preview (MP3)
		if (!empty($entry->mp3_path)) {
			$audioUrl = config("app.url") . "/storage/uploads/" . $entry->mp3_path;
			$fileName = $getCleanFileName($entry->mp3_path);

			$previewHtml .=
				'<div class="media-item audio-item">
                    <strong>' .
				htmlspecialchars($fileName) .
				'</strong>
                    <div class="audio-container">
                        <audio controls>
                            <source src="' .
				$audioUrl .
				'" type="audio/mpeg">
                            Your browser does not support the audio tag.
                        </audio>
                    </div>
                </div>';
		}

		// Add preview field if media found
		if (!empty($previewHtml)) {
			CRUD::addField([
				"name" => "media_preview",
				"type" => "custom_html",
				"tab" => "Gallery",
				"value" => '<div class="media-container">' . $previewHtml . "</div>",
			]);
		}
	}

	/**
	 * Configures media column display in list view
	 *
	 * Creates properly formatted previews for different types of media:
	 * - Images: Thumbnail with link to full image
	 * - Videos: Player with play button
	 * - Audio: Simple audio player
	 *
	 * @param string $column Column name
	 */
	public static function configureMediaColumnView($column)
	{
		$projectBaseUrl = config("app.url") . "/storage/uploads";

		CRUD::column($column)
			->label(ucfirst(str_replace("_path", "", $column)))
			->type("custom_html")
			->value(function ($entry) use ($column, $projectBaseUrl) {
				if (!empty($entry->$column)) {
					$fileUrl = $projectBaseUrl . "/" . $entry->$column;

					// Handle different file types
					if ($column === "image_path") {
						return '<a href="' .
							$fileUrl .
							'" target="_blank">
                                    <img src="' .
							$fileUrl .
							'" alt="Preview" 
                                         style="width: 150px; height: auto; border-radius: 3px;"
                                         loading="lazy"/>
                                </a>';
					} elseif (in_array($column, ["mp4_path", "webm_path", "ogv_path", "ogg_path"])) {
						// Define correct MIME type for each video format
						$mimeTypes = [
							"mp4_path" => "video/mp4",
							"webm_path" => "video/webm",
							"ogv_path" => "video/ogg",
							"ogg_path" => "video/ogg",
						];

						return '<div class="video-container" style="width: 150px; height: 100px; background: #333; display: flex; align-items: center; justify-content: center; position: relative; border-radius: 5px;">
                                    <button style="position: absolute; width: 40px; height: 40px; background: rgba(255, 255, 255, 0.7); border: none; border-radius: 50%; font-size: 20px; cursor: pointer; z-index: 2;"
                                        onclick="this.style.display=\'none\'; 
                                                 let video = this.nextElementSibling; 
                                                 let container = this.parentElement;
                                                 container.style.background=\'transparent\'; 
                                                 video.style.display=\'block\'; 
                                                 video.play(); 
                                                 video.controls = true;">
                                        ▶
                                    </button>
                                    <video 
                                        style="width: 100%; height: 100%; object-fit: cover; display: none;" 
                                        preload="none">
                                        <source src="' .
							$fileUrl .
							'" type="' .
							$mimeTypes[$column] .
							'">
                                        Your browser does not support the video tag.
                                    </video>
                                </div>';
					} elseif ($column === "mp3_path") {
						return '<audio controls style="width: 150px;" preload="none">
                                    <source src="' .
							$fileUrl .
							'" type="audio/mpeg">
                                    Your browser does not support the audio tag.
                                </audio>';
					}
				}
				return " - ";
			});
	}

	/**
	 * Adds informative descriptions to tabs
	 *
	 * Adds contextual explanations at the top of each tab
	 * for the media entity
	 *
	 * @param string $table Table name
	 */
	public static function addTabDescriptions($table)
	{
		if ($table != "media") {
			return;
		}

		$tabsAdded = [];
		$addTabDescription = function ($tab, $title, $description) use (&$tabsAdded) {
			if (!isset($tabsAdded[$tab])) {
				CRUD::addField([
					"name" => "custom_html_" . strtolower(str_replace(" ", "_", $tab)),
					"type" => "custom_html",
					"value" =>
						'<div class="p-3 mb-1 alert alert-info" style="border-left: 4px solid; background: #f8f9fa; border-radius: 5px;">
                        <h4 class="m-0 text-info">' .
						$title .
						'</h4>
                        <p class="mb-0 mt-2" >' .
						$description .
						'</p>
                    </div>',
					"tab" => $tab,
				]);
				$tabsAdded[$tab] = true;
			}
		};

		// Add descriptions to various tabs
		$addTabDescription("Uploads", trans("backpack::crud.disclaimer_title"), trans("backpack::crud.disclaimer_uploads"));
		$addTabDescription("Hero", trans("backpack::crud.disclaimer_title"), trans("backpack::crud.disclaimer_hero"));
		$addTabDescription("Gallery", trans("backpack::crud.disclaimer_title"), trans("backpack::crud.disclaimer_gallery"));
	}

	/**
	 * Assigns media fields to appropriate tabs
	 *
	 * Organizes fields into tabs based on their purpose:
	 * - *_path fields go to "Uploads" tab
	 * - caption field goes to "Caption" tab
	 *
	 * @param string $column Column name
	 * @param string $table Table name
	 * @param \Backpack\CRUD\app\Library\CrudPanel\CrudField $field Field to configure
	 */
	public static function assignMediaFieldTab($column, $table, $field)
	{
		if ($table != "media") {
			return;
		}

		if (str_contains($column, "path")) {
			$field->tab("Uploads");
		} elseif ($column == "caption") {
			$field->tab("Caption");
		}
	}
}
