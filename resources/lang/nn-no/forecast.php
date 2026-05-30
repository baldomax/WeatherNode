<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'iskaldt'],
        ['max' => 5,  'text' => 'kaldt'],
        ['max' => 12, 'text' => 'kjølig'],
        ['max' => 18, 'text' => 'mildt'],
        ['max' => 25, 'text' => 'varmt'],
        ['max' => INF,'text' => 'veldig varmt'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'stille til lett vind'],
        ['max' => 5.5,  'text' => 'en svak bris'],
        ['max' => 10.8, 'text' => 'en moderat bris'],
        ['max' => 17.2, 'text' => 'en stiv bris'],
        ['max' => 24.5, 'text' => 'stormende vind'],
        ['max' => INF,  'text' => 'stormvind'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'meget usannsynlig'],
        ['max' => 25,  'text' => 'en liten sjanse'],
        ['max' => 45,  'text' => 'en sjanse'],
        ['max' => 65,  'text' => 'sannsynlig'],
        ['max' => 85,  'text' => 'meget sannsynlig'],
        ['max' => INF, 'text' => 'nesten sikkert'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'klar himmel'],
        ['max' => 35,  'text' => 'overveiende solrikt'],
        ['max' => 70,  'text' => 'delvis skyet'],
        ['max' => 90,  'text' => 'overveiende skyet'],
        ['max' => INF, 'text' => 'overskyet'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'tørt'],
        ['max' => 1.0, 'text' => 'lett'],
        ['max' => 5.0, 'text' => 'moderat'],
        ['max' => INF, 'text' => 'kraftig'],
    ],
];
