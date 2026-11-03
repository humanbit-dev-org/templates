<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Contatto extends Model
{
    use CrudTrait, HasDisplayAttributes;

    protected $fillable = ['email', 'oggetto', 'messaggio'];

    protected $table = 'contatti';

    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        return $this->email;
    }
}
