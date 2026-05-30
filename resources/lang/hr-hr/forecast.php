<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'ledeno'],
        ['max' => 5,  'text' => 'hladno'],
        ['max' => 12, 'text' => 'svježe'],
        ['max' => 18, 'text' => 'blago'],
        ['max' => 25, 'text' => 'toplo'],
        ['max' => INF,'text' => 'vrlo toplo'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'tiho do laganog vjetra'],
        ['max' => 5.5,  'text' => 'slab povjetarac'],
        ['max' => 10.8, 'text' => 'umjeren povjetarac'],
        ['max' => 17.2, 'text' => 'jak povjetarac'],
        ['max' => 24.5, 'text' => 'olujni vjetar'],
        ['max' => INF,  'text' => 'vjetar olujne snage'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'vrlo malo vjerojatno'],
        ['max' => 25,  'text' => 'mala šansa'],
        ['max' => 45,  'text' => 'šansa'],
        ['max' => 65,  'text' => 'vjerojatno'],
        ['max' => 85,  'text' => 'vrlo vjerojatno'],
        ['max' => INF, 'text' => 'gotovo sigurno'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'vedro nebo'],
        ['max' => 35,  'text' => 'uglavnom sunčano'],
        ['max' => 70,  'text' => 'djelomično oblačno'],
        ['max' => 90,  'text' => 'uglavnom oblačno'],
        ['max' => INF, 'text' => 'oblačno'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'suho'],
        ['max' => 1.0, 'text' => 'slabo'],
        ['max' => 5.0, 'text' => 'umjereno'],
        ['max' => INF, 'text' => 'jako'],
    ],
];
