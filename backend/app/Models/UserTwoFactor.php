<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTwoFactor extends Model
{
    use CrudTrait;

    protected $fillable = [
        'id',
        'user_id',
        'device_id',
        'token',
        'token_expire',
        'token_verified',
        'trusted_until',
    ];

    /**
     * The token column holds a hash, never the digits that were mailed out.
     */
    protected $hidden = ['token'];

    protected function casts(): array
    {
        return [
            'token_expire' => 'datetime',
            'token_verified' => 'boolean',
            'trusted_until' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
