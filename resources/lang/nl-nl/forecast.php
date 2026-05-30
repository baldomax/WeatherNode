<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'ijskoud'],
        ['max' => 5,  'text' => 'koud'],
        ['max' => 12, 'text' => 'koel'],
        ['max' => 18, 'text' => 'zacht'],
        ['max' => 25, 'text' => 'warm'],
        ['max' => INF,'text' => 'heet'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'windstil tot lichte wind'],
        ['max' => 5.5,  'text' => 'een zwakke bries'],
        ['max' => 10.8, 'text' => 'een matige bries'],
        ['max' => 17.2, 'text' => 'een stevige bries'],
        ['max' => 24.5, 'text' => 'stormachtige wind'],
        ['max' => INF,  'text' => 'stormwind'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'zeer onwaarschijnlijk'],
        ['max' => 25,  'text' => 'een kleine kans'],
        ['max' => 45,  'text' => 'een kans'],
        ['max' => 65,  'text' => 'waarschijnlijk'],
        ['max' => 85,  'text' => 'zeer waarschijnlijk'],
        ['max' => INF, 'text' => 'bijna zeker'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'heldere hemel'],
        ['max' => 35,  'text' => 'overwegend zonnig'],
        ['max' => 70,  'text' => 'gedeeltelijk bewolkt'],
        ['max' => 90,  'text' => 'overwegend bewolkt'],
        ['max' => INF, 'text' => 'bewolkt'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'droog'],
        ['max' => 1.0, 'text' => 'licht'],
        ['max' => 5.0, 'text' => 'matig'],
        ['max' => INF, 'text' => 'zwaar'],
    ],
];
