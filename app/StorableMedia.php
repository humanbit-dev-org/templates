<?php

namespace App;

use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;

trait StorableMedia
{
	public function setAttribute($key, $value)
	{
		if ($this->isFileAttribute($key, $value)) {
			$disk = "uploads"; // Ensure this matches your filesystems config
			$destination_path = "media";

			// Sanitize filename
			$filename = preg_replace("/[^A-Za-z0-9\-\_\.]/", "_", $value->getClientOriginalName());

			// Store file and save the relative path
			$path = $value->storeAs($destination_path, $filename, $disk);
			$this->attributes[$key] = $path;

			return;
		}

		parent::setAttribute($key, $value);
	}

	public function getFileUrlAttribute($key)
	{
		return isset($this->attributes[$key]) ? asset("storage/uploads/" . $this->attributes[$key]) : null;
	}

	private function isFileAttribute($key, $value)
	{
		return Str::endsWith($key, "_path") && $value instanceof UploadedFile;
	}
}
