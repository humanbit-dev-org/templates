<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SeoMetaInformation extends Model
{
	use CrudTrait;
	use HasFactory;

	protected $fillable = ["it", "en", "image_path", "code", "page_id"];

	public function page()
	{
		return $this->belongsTo(Page::class);
	}
}
