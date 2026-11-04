<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\StorableMedia;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Media extends Model
{
    use CrudTrait, HasDisplayAttributes, StorableMedia;

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

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

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

    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        return $this->title;
    }
}
