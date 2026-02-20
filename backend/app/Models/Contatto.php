<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;

class Contatto extends Model
{
    use CrudTrait;

    protected $fillable = ['email', 'oggetto', 'messaggio'];

    protected $table = 'contatti';

    public function getDisplayAttribute()
    {
        return $this->email;
    }
}
