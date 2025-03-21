<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use App\StorableMedia;
use Illuminate\Database\Eloquent\Model;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Media extends Model
{
	use CrudTrait, StorableMedia;
	use HasFactory;

	protected $fillable = [
		"title",
		"image_path",
		"mp4_path",
		"ogg_path",
		"ogv_path",
		"webm_path",
		"mp3_path",
		"thought_id",
		"institutional_id",
		"president_id",
		"paragraph_id",
		"chapter_id",
		"page_id",
		"paragraph_layout",
		"full_width_bb",
		"caption",
		"caption_layout",
	];

	public function page()
	{
		return $this->belongsTo(Page::class);
	}
}
