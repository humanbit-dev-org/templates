<?php

namespace App\Models;

use App\Sortable;
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

		// Ensure 'home' page exists
		self::firstOrCreate(["name" => "home"], ["order" => 1]);

		// If directory doesn't exist, stop execution
		if (!File::exists($directoryPath)) {
			return;
		}

		// Get all existing backend page names
		$existingPages = self::pluck("name")->toArray();

		// Get frontend folders
		$directories = File::directories($directoryPath);
		$folderNames = array_map("basename", $directories);

		$folderNames = array_map(function ($folder) {
			return trim(strtolower($folder));
		}, $folderNames);

		// Ensure 'home' is not removed
		$folderNames[] = "home";

		// Pages to delete (exist in DB but not in frontend, excluding 'home')
		$pagesToDelete = array_diff($existingPages, $folderNames);

		// Pages to add (exist in frontend but not in DB)
		$pagesToAdd = array_diff($folderNames, $existingPages);

		// Add missing pages
		foreach ($pagesToAdd as $folderName) {
			$title = ucfirst(str_replace("-", " ", $folderName));
			$description = $descriptions[$folderName]["description"] ?? "";
			$order = $descriptions[$folderName]["order"] ?? self::max("order") + 1;

			self::create([
				"name" => $folderName,
				"title" => $title,
				"order" => $order,
			]);
		}

		// Delete pages that no longer exist in frontend, excluding 'home'
		self::whereIn("name", $pagesToDelete)->where("name", "!=", "home")->delete();
	}

	public function translates()
	{
		return $this->hasMany(Translate::class);
	}
}
