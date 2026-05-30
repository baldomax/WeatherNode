<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorLog extends Model
{
    protected $fillable = [
        'occurred_at',
        'path',
        'method',
        'status_code',
        'response_ms',
        'referrer_host',
        'search_engine',
        'search_terms',
        'country_code',
        'device_type',
        'browser_family',
        'os_family',
        'is_bot',
        'ip_hash',
        'ip_encrypted',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'is_bot' => 'boolean',
        'ip_encrypted' => 'encrypted',
    ];
}
