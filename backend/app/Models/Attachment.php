<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use App\Models\Traits\StorableAttachments;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
<<<<<<< HEAD
	use CrudTrait, StorableAttachments;

	protected $fillable = ["title", "description", "file_path", "institutional_id"];

	public function institutional()
	{
		return $this->belongsTo(Institutional::class);
	}
=======
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
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
