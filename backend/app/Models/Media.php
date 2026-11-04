<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\StorableMedia;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
	use CrudTrait, HasDisplayAttributes, StorableMedia;

<<<<<<< HEAD
	protected $fillable = [
		"title",
		"image_path",
		"mp4_path",
		"ogg_path",
		"ogv_path",
		"webm_path",
		"mp3_path",
		"institutional_id",
		"page_id",
		"caption",
	];
=======
    protected $fillable = [
        'title',
        'image_path',
        'mp4_path',
        'ogg_path',
        'ogv_path',
        'webm_path',
        'mp3_path',
        'article_id',
        'institutional_id',
        'page_id',
        'caption',
    ];
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	public function page()
	{
		return $this->belongsTo(Page::class);
	}

<<<<<<< HEAD
	public function institutional()
	{
		return $this->belongsTo(Institutional::class);
	}
=======
    /**
     * Article the media belongs to (nullable FK).
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    public function institutional()
    {
        return $this->belongsTo(Institutional::class);
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->title;
	}
}
