<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use App\Models\Traits\StorableMedia;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
	use CrudTrait;
	use HasFactory;
	use StorableMedia;

	protected $fillable = [
		"title_italian",
		"title_english",
		"meta_title_italian",
		"meta_title_english",
		"subtitle_italian",
		"subtitle_english",
		"abstract_italian",
		"abstract_english",
		"body_italian",
		"body_english",
		"meta_body_italian",
		"meta_body_english",
		"author",
		"published",
		"strillo",
	];

	public function media()
	{
		return $this->hasMany(Media::class);
	}

	public function attachment()
	{
		return $this->hasMany(Attachment::class);
	}

	/**
	 * Ritorna l'attributo da utilizzare per la visualizzazione nelle liste
	 */
	public function getDisplayAttribute()
	{
		return $this->title_italian;
	}
}
