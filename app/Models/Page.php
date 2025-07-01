<?php

namespace App\Models;

use App\Models\Traits\Sortable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Page extends Model
{
	use CrudTrait, Sortable;
	use HasFactory;

	protected $fillable = ["name", "title", "description", "order", "visible"];

	public function media()
	{
		return $this->hasMany(Media::class);
	}

	protected static function boot()
	{
		parent::boot();

		// Only run in web context (avoid running in migrations or CLI commands)
		if (app()->runningInConsole()) {
			return;
		}

		$projectRoot = base_path();
		$directoryPath = $projectRoot . "/frontend/src/app/[lang]";

		// If directory doesn't exist, stop execution
		if (!File::exists($directoryPath)) {
			return;
		}

		// Get all existing backend page names
		$existingPages = self::pluck("name")->toArray();

		// Get frontend folders, including pages inside group folders
		$directories = File::directories($directoryPath);
		$allFolders = [];

		foreach ($directories as $dir) {
			// Get all subdirectories inside this directory
			$subdirectories = File::directories($dir);

			// Add the folder itself (direct page)
			$folderName = basename($dir);
			if (!preg_match("/[\[\]()]/", $folderName)) {
				$allFolders[] = $folderName;
			}

			// Add subdirectories (pages inside group folders)
			foreach ($subdirectories as $subdir) {
				$subFolderName = basename($subdir);
				if (!preg_match("/[\[\]()]/", $subFolderName)) {
					$allFolders[] = $subFolderName;
				}
			}
		}

		// Normalize folder names (convert to lowercase and trim)
		$folderNames = array_map(function ($folder) {
			return trim(strtolower($folder));
		}, $allFolders);

		// Pages to delete (exist in DB but not in frontend)
		$pagesToDelete = array_diff($existingPages, $folderNames);

		// Pages to add (exist in frontend but not in DB)
		$pagesToAdd = array_diff($folderNames, $existingPages);

		// Predefined descriptions and order
		// $descriptions = [
		// 	"page-example" => [
		// 		"description" =>
		// 			"Lorem, ipsum dolor sit amet consectetur adipisicing elit. Provident atque nam dolorum quod quidem? Enim dolore fugit deleniti a nihil iure possimus facere sapiente molestiae adipisci, blanditiis tenetur. Eum, iusto?",
		// 		"order" => 1,
		// 	],
		// ];

		// Add missing pages
		foreach ($pagesToAdd as $folderName) {
			if (!self::where("name", $folderName)->exists()) {
				$title = ucfirst(str_replace("-", " ", $folderName));
				$description = $descriptions[$folderName]["description"] ?? "";
				$order = $descriptions[$folderName]["order"] ?? self::max("order") + 1;

				self::create([
					"name" => $folderName,
					"title" => $title,
					// "description" => $description,
					"order" => $order,
				]);
			}
		}

		// Delete pages that no longer exist in `frontend`
		self::whereIn("name", $pagesToDelete)->delete();

		// Clear page-related cache to ensure changes are reflected immediately
		cache()->forget("pages_list");
	}

	public function translates()
	{
		return $this->hasMany(Translate::class);
	}

	public function metadata()
	{
		return $this->hasMany(Metadata::class);
	}

	public function getDisplayAttribute()
	{
		return $this->title;
	}
}
