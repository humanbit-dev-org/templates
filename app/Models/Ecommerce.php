<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ecommerce extends Model
{
  use HasFactory;

  protected $fillable = ["name", "url", "showcase"];

  public $table = "ecommerce";

  public function orders()
  {
    return $this->hasMany(Order::class);
  }
}
