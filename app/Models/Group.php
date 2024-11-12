<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Group extends Model
{
  use HasFactory;

  protected $fillable = ["name", "creator_id", "category_id"];

  // Define the relationship with the group creator
  public function creator()
  {
    return $this->belongsTo(User::class, "creator_id");
  }

  // Define the relationship with the group category
  public function category()
  {
    return $this->belongsTo(Category::class);
  }

  // Many-to-many relationship with group member users
  public function members()
  {
    return $this->belongsToMany(User::class);
  }

  public function orders()
  {
    return $this->hasMany(Order::class);
  }
}
