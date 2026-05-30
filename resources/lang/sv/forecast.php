<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'iskallt'],
        ['max' => 5,  'text' => 'kallt'],
        ['max' => 12, 'text' => 'svalt'],
        ['max' => 18, 'text' => 'mildt'],
        ['max' => 25, 'text' => 'varmt'],
        ['max' => INF,'text' => 'mycket varmt'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'stilla till lätt vind'],
        ['max' => 5.5,  'text' => 'en svag bris'],
        ['max' => 10.8, 'text' => 'en måttlig bris'],
        ['max' => 17.2, 'text' => 'en styv bris'],
        ['max' => 24.5, 'text' => 'stormig vind'],
        ['max' => INF,  'text' => 'stormvind'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'mycket osannolikt'],
        ['max' => 25,  'text' => 'en liten chans'],
        ['max' => 45,  'text' => 'en chans'],
        ['max' => 65,  'text' => 'sannolikt'],
        ['max' => 85,  'text' => 'mycket sannolikt'],
        ['max' => INF, 'text' => 'nästan säkert'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'klar himmel'],
        ['max' => 35,  'text' => 'övervägande soligt'],
        ['max' => 70,  'text' => 'delvis molnigt'],
        ['max' => 90,  'text' => 'övervägande molnigt'],
        ['max' => INF, 'text' => 'mulet'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'torrt'],
        ['max' => 1.0, 'text' => 'lätt'],
        ['max' => 5.0, 'text' => 'måttligt'],
        ['max' => INF, 'text' => 'kraftigt'],
    ],
];
