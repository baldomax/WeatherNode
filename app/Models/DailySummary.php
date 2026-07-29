<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class DailySummary extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'temp_high',
        'temp_high_time',
        'temp_low',
        'temp_low_time',
        'temp_avg',
        'humidity_high',
        'humidity_low',
        'humidity_avg',
        'pressure_high',
        'pressure_low',
        'pressure_avg',
        'wind_max',
        'wind_max_time',
        'wind_avg',
        'wind_dominant_direction',
        'rain_total',
        'rain_rate_max',
        'uv_max',
        'solar_max',
        'solar_hours',
        'heating_degree_days',
        'cooling_degree_days',
    ];

    protected $casts = [
        'date' => 'date',
        'temp_high_time' => 'datetime:H:i',
        'temp_low_time' => 'datetime:H:i',
        'wind_max_time' => 'datetime:H:i',
        'temp_high' => 'float',
        'temp_low' => 'float',
        'temp_avg' => 'float',
        'wind_max' => 'float',
        'rain_total' => 'float',
        'solar_hours' => 'float',
    ];

    /**
     * Get summary for a specific date
     */
    public static function forDate($date): ?self
    {
        return static::whereDate('date', $date)->first();
    }

    /**
     * Get summaries for a month
     */
    public function scopeForMonth($query, $year, $month)
    {
        return $query->whereYear('date', $year)->whereMonth('date', $month);
    }

    /**
     * Get summaries for a year
     */
    public function scopeForYear($query, $year)
    {
        return $query->whereYear('date', $year);
    }

    /**
     * Calculate monthly statistics
     */
    public static function monthlyStats($year, $month): array
    {
        $summaries = static::forMonth($year, $month)->get();
        
        return [
            'temp_high_max' => $summaries->max('temp_high'),
            'temp_low_min' => $summaries->min('temp_low'),
            'temp_avg' => $summaries->avg('temp_avg'),
            'rain_total' => $summaries->sum('rain_total'),
            'wind_max' => $summaries->max('wind_max'),
            'days_with_rain' => $summaries->where('rain_total', '>', 0)->count(),
        ];
    }
}
