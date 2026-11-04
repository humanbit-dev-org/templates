<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\StorableAttachments;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use CrudTrait, HasDisplayAttributes, StorableAttachments;

    protected $fillable = ['title', 'description', 'file_path', 'article_id', 'institutional_id'];

    /**
     * Article the attachment belongs to (nullable FK).
     */
    public function article()
    {
        return $this->belongsTo(Article::class);
    }

    /**
     * Institutional page the attachment belongs to (nullable FK).
     */
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
