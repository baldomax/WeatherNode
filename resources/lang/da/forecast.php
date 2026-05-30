<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'iskold'],
        ['max' => 5,  'text' => 'kold'],
        ['max' => 12, 'text' => 'kølig'],
        ['max' => 18, 'text' => 'mild'],
        ['max' => 25, 'text' => 'varm'],
        ['max' => INF,'text' => 'meget varm'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'stille til let vind'],
        ['max' => 5.5,  'text' => 'en svag brise'],
        ['max' => 10.8, 'text' => 'en moderat brise'],
        ['max' => 17.2, 'text' => 'en stiv brise'],
        ['max' => 24.5, 'text' => 'stormende vind'],
        ['max' => INF,  'text' => 'stormvind'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'meget usandsynligt'],
        ['max' => 25,  'text' => 'en lille chance'],
        ['max' => 45,  'text' => 'en chance'],
        ['max' => 65,  'text' => 'sandsynligt'],
        ['max' => 85,  'text' => 'meget sandsynligt'],
        ['max' => INF, 'text' => 'næsten sikkert'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'klar himmel'],
        ['max' => 35,  'text' => 'overvejende solrigt'],
        ['max' => 70,  'text' => 'delvist skyet'],
        ['max' => 90,  'text' => 'overvejende skyet'],
        ['max' => INF, 'text' => 'overskyet'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'tørt'],
        ['max' => 1.0, 'text' => 'let'],
        ['max' => 5.0, 'text' => 'moderat'],
        ['max' => INF, 'text' => 'kraftigt'],
    ],
];
