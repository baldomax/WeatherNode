<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VisitorDailyStat extends Model
{
    protected $fillable = [
        'date',
        'pageviews',
        'uniques',
        'total_response_ms',
        'avg_response_ms',
        'status_codes',
        'top_pages',
        'referrers',
        'countries',
        'devices',
        'browsers',
        'oses',
        'search_engines',
        'search_terms',
    ];

    protected $casts = [
        'date' => 'date',
        'status_codes' => 'array',
        'top_pages' => 'array',
        'referrers' => 'array',
        'countries' => 'array',
        'devices' => 'array',
        'browsers' => 'array',
        'oses' => 'array',
        'search_engines' => 'array',
        'search_terms' => 'array',
    ];
}
