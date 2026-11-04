<?php

namespace App\Models;

use App\Models\Traits\HasDisplayAttributes;
use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserTwoFactor extends Model
{
    use CrudTrait, HasDisplayAttributes;

    protected $fillable = [
        'username',
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

    /**
     * Owner of the device record (user_two_factors.username → users.username).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'username', 'username');
    }

    // see HasDisplayAttributes for full doc
    public function getDisplayAttribute(): string
    {
        return $this->username.' - '.$this->device_id;
    }
}
