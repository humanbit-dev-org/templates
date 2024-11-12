<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderUser extends Model
{
  use HasFactory;

  protected $fillable = ["order_id", "user_id", "import", "percentage"];

  public $table = "order_user";

  public function order()
  {
    return $this->belongsTo(Order::class);
  }

  public function user()
  {
    return $this->belongsTo(User::class);
  }
}
