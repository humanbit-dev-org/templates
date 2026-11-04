<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\StorableMedia;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Article extends Model
{
	use CrudTrait, HasDisplayAttributes, HasFactory, StorableMedia;

	/**
	 * The attributes that are mass assignable (columns of the articles table).
	 *
	 * @var array<int, string>
	 */
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

	protected function casts(): array
	{
		return [
			"published" => "boolean",
			"strillo" => "boolean",
		];
	}

	/**
	 * Media files attached to the article (media.article_id).
	 */
	public function media()
	{
		return $this->hasMany(Media::class);
	}

	/**
	 * Downloadable attachments of the article (attachments.article_id).
	 */
	public function attachments()
	{
		return $this->hasMany(Attachment::class);
	}

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->title_italian;
	}
}
