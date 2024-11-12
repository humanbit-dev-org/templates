<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
  use HasFactory;

  protected $fillable = ["group_id", "ecommerce_id"];

  public function group()
  {
    return $this->belongsTo(Group::class);
  }

  public function ecommerce()
  {
    return $this->belongsTo(Ecommerce::class);
  }

  public function details()
  {
    return $this->hasMany(OrderUser::class);
  }
}
