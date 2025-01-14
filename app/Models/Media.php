<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = [
		"title",
		"description",
		"image_path",
		"mp4_path",
		"ogv_path",
		"webm_path",
		"article_id",
		"institutional_id",
	];

	public function setImagePathAttribute($value)
	{
		$attribute_name = "image_path";
		$disk = "uploads"; // Make sure this matches your configuration in filesystems.php
		$destination_path = "media";

		if ($value && is_a($value, \Illuminate\Http\UploadedFile::class)) {
			// Log the file details before saving it
			\Log::info("Attempting to store file:", [
				"file" => $value->getClientOriginalName(),
				"path" => storage_path("app/public/uploads/" . $destination_path),
			]);

			// Try storing the file and capture the path
			$path = $value->store($destination_path, $disk);

			// Check if the path was returned
			\Log::info("File stored at:", ["path" => $path]);

			// Save the relative file path
			$this->attributes[$attribute_name] = $path;
		} else {
			$this->attributes[$attribute_name] = $value;
		}
	}

	public function getImageUrlAttribute()
	{
		return $this->image_path ? asset("storage/uploads/" . $this->image_path) : null;
	}

	public function article()
	{
		return $this->belongsTo(Article::class);
	}

	public function institutional()
	{
		return $this->belongsTo(Institutional::class);
	}
}
