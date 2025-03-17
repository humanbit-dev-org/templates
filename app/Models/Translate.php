<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Services\TranslateExportService;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Translate extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["it", "en", "text_it", "text_en", "code", "page_id"];

	protected static function booted()
	{
		static::saved(function () {
			TranslateExportService::exportTranslations();
		});

		static::deleted(function () {
			TranslateExportService::exportTranslations();
		});
	}

	public function page()
	{
		return $this->belongsTo(Page::class);
	}
}
