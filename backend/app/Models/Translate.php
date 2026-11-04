<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Translate extends Model
{
	use CrudTrait, HasDisplayAttributes;

	protected $fillable = ["it", "en", "text_it", "text_en", "code", "page_id", "banca_abi"];

	public function page()
	{
		return $this->belongsTo(Page::class);
	}

	public function banca()
	{
		return $this->belongsTo(Banca::class, "banca_abi", "banca_abi");
	}

	// see HasDisplayAttributes for full doc
	public function getDisplayAttribute(): string
	{
		return $this->code;
	}
}
