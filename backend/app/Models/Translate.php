<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Translate extends Model
{
	use CrudTrait, HasDisplayAttributes;

<<<<<<< HEAD
	protected $fillable = ["it", "en", "text_it", "text_en", "code", "page_id", "banca_abi"];
=======
    protected $fillable = ['it', 'en', 'text_it', 'text_en', 'code', 'page_id'];
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb

	public function page()
	{
		return $this->belongsTo(Page::class);
	}

<<<<<<< HEAD
	public function banca()
	{
		return $this->belongsTo(Banca::class, "banca_abi", "banca_abi");
	}

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->code;
	}
=======
    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        return $this->code;
    }
>>>>>>> 100810b09b622bfe1cda570c0a4e4e2dcbd814cb
}
