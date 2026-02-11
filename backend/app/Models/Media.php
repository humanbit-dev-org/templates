<?php

namespace App\Models;

use App\Models\Traits\StorableMedia;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use CrudTrait, StorableMedia;

	protected $fillable = [
		"title",
		"image_path",
		"mp4_path",
		"ogg_path",
		"ogv_path",
		"webm_path",
		"mp3_path",
		"article_id",
		"institutional_id",
		"page_id",
		"caption",
	];

	public function page()
	{
		return $this->belongsTo(Page::class);
	}

	public function article()
	{
		return $this->belongsTo(Article::class);
	}

	public function institutional()
	{
		return $this->belongsTo(Institutional::class);
	}

	public function getDisplayAttribute()
	{
		return $this->title;
	}
}
