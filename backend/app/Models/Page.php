<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\Sortable;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;

class Page extends Model
{
    use CrudTrait, HasDisplayAttributes, Sortable;

    protected $fillable = ['name', 'title', 'description', 'order', 'visible'];

    public function media()
    {
        return $this->hasMany(Media::class);
    }

    protected static function booted(): void
    {
        // Only run in web context (avoid running in migrations or CLI commands)
        if (app()->runningInConsole()) {
            return;
        }

        $directoryPath = base_path().'/../frontend/src/app/[lang]';

        if (! File::exists($directoryPath)) {
            return;
        }

        $existingPages = self::pluck('name')->toArray();

        $directories = File::directories($directoryPath);
        $allFolders = [];

        foreach ($directories as $dir) {
            $subdirectories = File::directories($dir);

            $folderName = basename($dir);
            if (! preg_match("/[\[\]()]/", $folderName)) {
                $allFolders[] = $folderName;
            }

            foreach ($subdirectories as $subdir) {
                $subFolderName = basename($subdir);
                if (! preg_match("/[\[\]()]/", $subFolderName)) {
                    $allFolders[] = $subFolderName;
                }
            }
        }

        $folderNames = array_map(
            fn ($folder) => trim(strtolower($folder)),
            $allFolders
        );

        $pagesToDelete = array_diff($existingPages, $folderNames);
        $pagesToAdd = array_diff($folderNames, $existingPages);

        foreach ($pagesToAdd as $folderName) {
            if (! self::where('name', $folderName)->exists()) {
                self::create([
                    'name' => $folderName,
                    'title' => ucfirst(str_replace('-', ' ', $folderName)),
                    'order' => self::max('order') + 1,
                ]);
            }
        }

        self::whereIn('name', $pagesToDelete)->delete();

        cache()->forget('pages_list');
    }

    public function translates()
    {
        return $this->hasMany(Translate::class);
    }

    public function metadata()
    {
        return $this->hasMany(Metadata::class);
    }

    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        return $this->title;
    }
}
