<?php

namespace App\Models;

use App\StorableAttachments;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
	use CrudTrait, StorableAttachments;
	use HasFactory;

	protected $fillable = ["title", "description", "file_path", "article_id", "institutional_id"];

	public function article()
	{
		return $this->belongsTo(Article::class);
	}

	public function institutional()
	{
		return $this->belongsTo(Institutional::class);
	}
}
