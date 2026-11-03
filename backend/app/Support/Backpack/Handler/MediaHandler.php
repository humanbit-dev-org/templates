<?php

namespace App\Support\Backpack\Handler;

use App\Support\Url\StoredFileUrl;
use Backpack\CRUD\app\Library\CrudPanel\CrudField;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Database\Eloquent\Model;

/**
 * Handles media files across the application.
 *
 * Provides functionality for file uploads, media previews,
 * and proper display of different media types (images,
 * videos, audio) in both form and list views.
 *
 * Supports both local storage (development) and S3/CloudFront (production)
 * based on the FILESYSTEM_DISK environment variable.
 */
class MediaHandler
{
    // Array of all file types and their corresponding MIME types (key: column name or substring match)
    public static $uploadFields = [
        'image_path' => 'image/*',
        'logo_path' => 'image/*',
        'font_primary_path' => 'font/*',
        'font_secondary_path' => 'font/*',
        'font_tertiary_path' => 'font/*',
        'mp4_path' => 'video/mp4',
        'ogv_path' => 'video/ogg',
        'webm_path' => 'video/webm',
        'mp3_path' => 'audio/mpeg',
        'ogg_path' => 'video/ogg',
        'file_path' => 'application/pdf',
    ];

    // =========================================================
    // URL resolution
    // ---------------------------------------------------------
    // Centralised URL generation — disk-agnostic.
    // In production (s3): uses CloudFront URL.
    // In development (local): uses local public storage URL.
    // =========================================================

    /**
     * Resolve the public URL for a stored media file.
     *
     * @param  string  $path  Relative path as stored in DB (e.g. logos/logo.png)
     * @return string Full public URL
     */
    public static function resolveFileUrl(string $path): string
    {
        return StoredFileUrl::resolveForAdminPreview($path);
    }

    /**
     * Resolve the storage disk based on the application filesystem configuration.
     * Driven by FILESYSTEM_DISK in .env — no hardcoded disk names in business logic.
     *
     * @return string Disk name (e.g. 's3' or 'local')
     */
    public static function resolveDisk(): string
    {
        return config('filesystems.default');
    }

    // =========================================================
    // Upload field configuration
    // =========================================================

    /**
     * Configures upload fields for media columns.
     *
     * Sets the field type to "upload" and configures appropriate
     * options based on the media type (image, video, audio, file).
     *
     * @param  string  $column  Column name
     * @param  CrudField  $field  Field to configure
     * @return bool True if the field was configured as upload field, false otherwise
     */
    public static function configureUploadField($column, $field): bool
    {
        foreach (self::$uploadFields as $upload => $mime) {
            if (str_contains($column, $upload)) {
                $field->upload(true);

                $labelKey = str_replace('_path', '', $upload);
                $field->label('Upload '.ucfirst($labelKey));

                // Use the resolved disk — s3 in production, local in development
                $field->disk(self::resolveDisk());

                if (in_array($column, ['font_primary_path', 'font_secondary_path', 'font_tertiary_path'])) {
                    $mime = '.woff,.woff2,.ttf,.otf';
                }

                $field->attributes(['accept' => $mime]);

                return true;
            }
        }

        return false;
    }

    // =========================================================
    // Media previews (edit form)
    // =========================================================

    /**
     * Adds media previews to the "Gallery" tab (edit mode only).
     *
     * Generates HTML preview elements for images, videos and audio files
     * attached to the current model entry.
     *
     * @param  Model  $model  Current model
     */
    public static function addMediaPreviews(Model $model): void
    {
        $entryId = CRUD::getCurrentEntryId();
        if (! $entryId) {
            return;
        }

        $entry = $model->find($entryId);
        $previewHtml = '';

        $getCleanFileName = function (string $filePath): string {
            return str_replace('media/', '', basename($filePath));
        };

        // Generate image preview (image_path and logo_path)
        foreach (['image_path', 'logo_path'] as $imageColumn) {
            if (! empty($entry->$imageColumn)) {
                $imageUrl = self::resolveFileUrl($entry->$imageColumn);
                $fileName = $getCleanFileName($entry->$imageColumn);

                $previewHtml .= '
                    <div class="media-item">
                        <strong>'.htmlspecialchars($fileName).'</strong>
                        <a href="'.$imageUrl.'" target="_blank" rel="noopener noreferrer">
                            <img src="'.$imageUrl.'" alt="Preview"
                                 style="max-height:80px;width:auto;border-radius:4px;"/>
                        </a>
                    </div>';
            }
        }

        // Generate video previews (MP4, WebM, OGV, OGG)
        foreach (['mp4_path' => 'mp4', 'webm_path' => 'webm', 'ogv_path' => 'ogg', 'ogg_path' => 'ogg'] as $column => $mimeType) {
            if (! empty($entry->$column)) {
                $videoUrl = self::resolveFileUrl($entry->$column);
                $fileName = $getCleanFileName($entry->$column);

                $previewHtml .= '
                    <div class="media-item">
                        <strong>'.htmlspecialchars($fileName).'</strong>
                        <video controls>
                            <source src="'.$videoUrl.'" type="video/'.$mimeType.'">
                            Your browser does not support the video tag.
                        </video>
                    </div>';
            }
        }

        // Generate audio preview (MP3)
        if (! empty($entry->mp3_path)) {
            $audioUrl = self::resolveFileUrl($entry->mp3_path);
            $fileName = $getCleanFileName($entry->mp3_path);

            $previewHtml .= '
                <div class="media-item audio-item">
                    <strong>'.htmlspecialchars($fileName).'</strong>
                    <div class="audio-container">
                        <audio controls>
                            <source src="'.$audioUrl.'" type="audio/mpeg">
                            Your browser does not support the audio tag.
                        </audio>
                    </div>
                </div>';
        }

        // Add preview field if media found
        if (! empty($previewHtml)) {
            CRUD::addField([
                'name' => 'media_preview',
                'type' => 'custom_html',
                'tab' => 'Gallery',
                'value' => '<div class="media-container">'.$previewHtml.'</div>',
            ]);
        }
    }

    // =========================================================
    // List view column configuration
    // =========================================================

    /**
     * Configures media column display in list view.
     *
     * Creates properly formatted previews for different types of media:
     * - Images: Thumbnail with link to full image
     * - Videos: Player with play button
     * - Audio: Simple audio player
     *
     * @param  string  $column  Column name
     */
    public static function configureMediaColumnView(string $column): void
    {
        CRUD::column($column)
            ->label(ucfirst(str_replace('_path', '', $column)))
            ->type('custom_html')
            ->value(function ($entry) use ($column) {
                if (empty($entry->$column)) {
                    return ' - ';
                }

                // Resolve URL through the centralised method — disk-agnostic
                $fileUrl = self::resolveFileUrl($entry->$column);

                // Image columns: thumbnail with link to full image
                if (in_array($column, ['image_path', 'logo_path'])) {
                    return '<a href="'.$fileUrl.'" target="_blank" rel="noopener noreferrer">
                                <img src="'.$fileUrl.'" alt="Preview"
                                     style="max-width:150px;max-height:40px;height:auto;border-radius:3px;"
                                     loading="lazy"/>
                            </a>';
                }

                // Video columns: lazy-loaded player with play button overlay
                if (in_array($column, ['mp4_path', 'webm_path', 'ogv_path', 'ogg_path'])) {
                    $mimeTypes = [
                        'mp4_path' => 'video/mp4',
                        'webm_path' => 'video/webm',
                        'ogv_path' => 'video/ogg',
                        'ogg_path' => 'video/ogg',
                    ];

                    return '<div class="video-container"
                                 style="width:150px;height:100px;background:#333;display:flex;
                                        align-items:center;justify-content:center;
                                        position:relative;border-radius:5px;">
                                <button style="position:absolute;width:40px;height:40px;
                                               background:rgba(255,255,255,0.7);border:none;
                                               border-radius:50%;font-size:20px;cursor:pointer;z-index:2;"
                                    onclick="this.style.display=\'none\';
                                             let v=this.nextElementSibling;
                                             this.parentElement.style.background=\'transparent\';
                                             v.style.display=\'block\';
                                             v.play();
                                             v.controls=true;">
                                    ▶
                                </button>
                                <video style="width:100%;height:100%;object-fit:cover;display:none;"
                                       preload="none">
                                    <source src="'.$fileUrl.'" type="'.$mimeTypes[$column].'">
                                    Your browser does not support the video tag.
                                </video>
                            </div>';
                }

                // Audio column: inline player
                if ($column === 'mp3_path') {
                    return '<audio controls style="width:150px;" preload="none">
                                <source src="'.$fileUrl.'" type="audio/mpeg">
                                Your browser does not support the audio tag.
                            </audio>';
                }

                return ' - ';
            });
    }

    // =========================================================
    // Tab helpers
    // =========================================================

    /**
     * Adds informative descriptions to tabs.
     *
     * Adds contextual explanations at the top of each tab
     * for the media entity.
     *
     * @param  string  $table  Table name
     */
    public static function addTabDescriptions(string $table): void
    {
        if ($table !== 'media') {
            return;
        }

        $tabsAdded = [];
        $addTabDescription = function (string $tab, string $title, string $description) use (&$tabsAdded): void {
            if (isset($tabsAdded[$tab])) {
                return;
            }

            CRUD::addField([
                'name' => 'custom_html_'.strtolower(str_replace(' ', '_', $tab)),
                'type' => 'custom_html',
                'value' => '<div class="p-3 mb-1 alert alert-info"
                                 style="border-left:4px solid;background:#f8f9fa;border-radius:5px;">
                                <h4 class="m-0 text-info">'.$title.'</h4>
                                <p class="mb-0 mt-2">'.$description.'</p>
                            </div>',
                'tab' => $tab,
            ]);

            $tabsAdded[$tab] = true;
        };

        $addTabDescription('Uploads', trans('backpack::crud.disclaimer_title'), trans('backpack::crud.disclaimer_uploads'));
        $addTabDescription('Hero', trans('backpack::crud.disclaimer_title'), trans('backpack::crud.disclaimer_hero'));
        $addTabDescription('Gallery', trans('backpack::crud.disclaimer_title'), trans('backpack::crud.disclaimer_gallery'));
    }

    /**
     * Assigns media fields to appropriate tabs.
     *
     * Organizes fields into tabs based on their purpose:
     * - *_path fields go to "Uploads" tab
     * - caption field goes to "Caption" tab
     * - Other fields (like title) go to "Dati" tab
     *
     * @param  string  $column  Column name
     * @param  string  $table  Table name
     * @param  CrudField  $field  Field to configure
     */
    public static function assignMediaFieldTab(string $column, string $table, $field): void
    {
        if ($table !== 'media') {
            return;
        }

        if (str_contains($column, 'path')) {
            $field->tab('Uploads');
        } elseif ($column === 'caption') {
            $field->tab('Caption');
        } else {
            $field->tab('Dati');
        }
    }
}
