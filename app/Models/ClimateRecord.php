<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ClimateRecord extends Model
{
    use HasFactory;

    protected $fillable = [
        'month',
        'day',
        'record_high',
        'record_high_year',
        'record_low',
        'record_low_year',
        'avg_high',
        'avg_low',
        'avg_temp',
        'avg_precipitation',
        'record_wind',
        'record_wind_year',
        'record_rain',
        'record_rain_year',
    ];

    protected $casts = [
        'record_high' => 'float',
        'record_low' => 'float',
        'avg_high' => 'float',
        'avg_low' => 'float',
        'avg_temp' => 'float',
        'avg_precipitation' => 'float',
        'record_wind' => 'float',
        'record_rain' => 'float',
    ];

    /**
     * Get climate record for a specific day
     */
    public static function forDay($month, $day): ?self
    {
        return static::where('month', $month)->where('day', $day)->first();
    }

    /**
     * Get climate record for today
     */
    public static function forToday(): ?self
    {
        return static::forDay(now()->month, now()->day);
    }

    /**
     * Check if a temperature is a new record
     */
    public function isNewHighRecord(float $temperature): bool
    {
        return $this->record_high === null || $temperature > $this->record_high;
    }

    /**
     * Check if a temperature is a new low record
     */
    public function isNewLowRecord(float $temperature): bool
    {
        return $this->record_low === null || $temperature < $this->record_low;
    }

    /**
     * Update records if new extremes are found
     */
    public function updateIfRecord(float $high, float $low, ?float $wind = null, ?float $rain = null): bool
    {
        $updated = false;

        if ($this->isNewHighRecord($high)) {
            $this->record_high = $high;
            $this->record_high_year = now()->year;
            $updated = true;
        }

        if ($this->isNewLowRecord($low)) {
            $this->record_low = $low;
            $this->record_low_year = now()->year;
            $updated = true;
        }

        if ($wind !== null && ($this->record_wind === null || $wind > $this->record_wind)) {
            $this->record_wind = $wind;
            $this->record_wind_year = now()->year;
            $updated = true;
        }

        if ($rain !== null && ($this->record_rain === null || $rain > $this->record_rain)) {
            $this->record_rain = $rain;
            $this->record_rain_year = now()->year;
            $updated = true;
        }

        if ($updated) {
            $this->save();
        }

        return $updated;
    }
}
