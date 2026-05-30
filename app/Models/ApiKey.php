<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKey extends Model
{
    protected $fillable = [
        'name',
        'key_hash',
        'key_prefix',
        'key_encrypted',
        'is_public',
        'rate_limit_per_minute',
        'last_used_at',
        'revoked_at',
    ];

    protected $casts = [
        'is_public' => 'boolean',
        'rate_limit_per_minute' => 'integer',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];
}
