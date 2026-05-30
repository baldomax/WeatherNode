<?php

return [
    // Temperature adjectives (optional; used when you want "mild/cold" wording)
    'temp' => [
        ['max' => 0,  'text' => 'freezing'],
        ['max' => 5,  'text' => 'cold'],
        ['max' => 12, 'text' => 'cool'],
        ['max' => 18, 'text' => 'mild'],
        ['max' => 25, 'text' => 'warm'],
        ['max' => INF,'text' => 'hot'],
    ],

    // Wind categories (m/s)
    'wind' => [
        ['max' => 1.5,  'text' => 'calm to light wind'],
        ['max' => 5.5,  'text' => 'a gentle breeze'],
        ['max' => 10.8, 'text' => 'a moderate breeze'],
        ['max' => 17.2, 'text' => 'a strong breeze'],
        ['max' => 24.5, 'text' => 'gale-force winds'],
        ['max' => INF,  'text' => 'storm-force winds'],
    ],

    // Precip probability labels (percent)
    'precip_prob' => [
        ['max' => 10,  'text' => 'very unlikely'],
        ['max' => 25,  'text' => 'a small chance'],
        ['max' => 45,  'text' => 'a chance'],
        ['max' => 65,  'text' => 'likely'],
        ['max' => 85,  'text' => 'very likely'],
        ['max' => INF, 'text' => 'almost certain'],
    ],

    // Cloud cover labels (percent)
    'sky' => [
        ['max' => 10,  'text' => 'clear skies'],
        ['max' => 35,  'text' => 'mostly sunny'],
        ['max' => 70,  'text' => 'partly cloudy'],
        ['max' => 90,  'text' => 'mostly cloudy'],
        ['max' => INF, 'text' => 'overcast'],
    ],

    // Precip intensity by mm in a period/day (tweak per your forecast granularity)
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'dry'],
        ['max' => 1.0, 'text' => 'light'],
        ['max' => 5.0, 'text' => 'moderate'],
        ['max' => INF, 'text' => 'heavy'],
    ],

    // Change thresholds (for aggregation)
    'thresholds' => [
        'wind_change_ms' => 3.0,    // mention wind change if differs by >= this
        'temp_change_c'  => 5.0,    // mention significant temperature changes
        'precip_change_pct' => 30.0 // mention if precip probability shifts big
    ],
];
