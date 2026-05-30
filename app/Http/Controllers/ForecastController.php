<?php

namespace App\Http\Controllers;

use App\Contracts\Nlg\Narrator;
use Illuminate\Http\Request;

class ForecastController extends Controller
{
    public function narrate(Request $request, Narrator $narrator)
    {
        $data = $request->validate([
            'date' => ['nullable', 'string'],
            'min_temp_c' => ['nullable', 'numeric'],
            'max_temp_c' => ['nullable', 'numeric'],
            'wind_ms' => ['nullable', 'numeric'],
            'wind_dir_deg' => ['nullable', 'numeric'],
            'precip_prob_pct' => ['nullable', 'numeric'],
            'precip_mm' => ['nullable', 'numeric'],
            'precip_type' => ['nullable', 'string'],
            'cloud_pct' => ['nullable', 'numeric'],
            'periods' => ['nullable', 'array'],
            'periods.*.name' => ['nullable', 'string'],
            'periods.*.temp_c' => ['nullable', 'numeric'],
            'periods.*.wind_ms' => ['nullable', 'numeric'],
            'periods.*.wind_dir_deg' => ['nullable', 'numeric'],
            'periods.*.precip_prob_pct' => ['nullable', 'numeric'],
            'periods.*.precip_mm' => ['nullable', 'numeric'],
            'periods.*.precip_type' => ['nullable', 'string'],
            'periods.*.cloud_pct' => ['nullable', 'numeric'],
        ]);

        $text = $narrator->narrate($data);

        return response()->json([
            'date' => $data['date'] ?? null,
            'text' => $text,
        ]);
    }
}
