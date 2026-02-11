<?php

namespace App\Models;

use App\Models\Traits\StorableAttachments;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Attachment extends Model
{
    use CrudTrait, StorableAttachments;

    protected $fillable = ['title', 'description', 'file_path', 'institutional_id'];

    public function institutional()
    {
        return $this->belongsTo(Institutional::class);
    }
}
