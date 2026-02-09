<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Metadata extends Model
{
    use CrudTrait;

    protected $fillable = ['it', 'en', 'image_path', 'code', 'page_id'];

    public function page()
    {
        return $this->belongsTo(Page::class);
    }

    public function getDisplayAttribute()
    {
        return $this->code;
    }
}
