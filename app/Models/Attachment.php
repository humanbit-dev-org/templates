<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["title", "description", "file_path", "article_id", "institutional_id"];

	public function setFilePathAttribute($value)
	{
		// Correct method name to match file_path
		$attribute_name = "file_path";
		$disk = "uploads";
		$destination_path = "attachments";

		if ($value && is_a($value, \Illuminate\Http\UploadedFile::class)) {
			\Log::info("Attempting to store file:", [
				"file" => $value->getClientOriginalName(),
				"path" => storage_path("app/public/uploads/" . $destination_path),
			]);

			$path = $value->store($destination_path, $disk);
			\Log::info("File stored at:", ["path" => $path]);

			$this->attributes[$attribute_name] = $path;
		} else {
			$this->attributes[$attribute_name] = $value;
		}
	}

	public function getFileUrlAttribute()
	{
		return $this->file_path ? asset("storage/uploads/" . $this->file_path) : null;
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
