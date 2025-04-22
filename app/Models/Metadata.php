<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Services\MetadataExportService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Metadata extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["it", "en", "image_path", "code", "page_id"];

	protected static function booted()
	{
		static::saved(function () {
			MetadataExportService::exportMetadata();
		});

		static::deleted(function () {
			MetadataExportService::exportMetadata();
		});
	}

	public function page()
	{
		return $this->belongsTo(Page::class);
	}

	public function getDisplayAttribute()
	{
		return $this->code;
	}
}
