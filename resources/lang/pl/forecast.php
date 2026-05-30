<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'mroźno'],
        ['max' => 5,  'text' => 'zimno'],
        ['max' => 12, 'text' => 'chłodno'],
        ['max' => 18, 'text' => 'łagodnie'],
        ['max' => 25, 'text' => 'ciepło'],
        ['max' => INF,'text' => 'gorąco'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'cisza do lekkiego wiatru'],
        ['max' => 5.5,  'text' => 'lekka bryza'],
        ['max' => 10.8, 'text' => 'umiarkowana bryza'],
        ['max' => 17.2, 'text' => 'silna bryza'],
        ['max' => 24.5, 'text' => 'wietrznie'],
        ['max' => INF,  'text' => 'wiatr sztormowy'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'bardzo mało prawdopodobne'],
        ['max' => 25,  'text' => 'mała szansa'],
        ['max' => 45,  'text' => 'szansa'],
        ['max' => 65,  'text' => 'prawdopodobne'],
        ['max' => 85,  'text' => 'bardzo prawdopodobne'],
        ['max' => INF, 'text' => 'prawie pewne'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'czyste niebo'],
        ['max' => 35,  'text' => 'przeważnie słonecznie'],
        ['max' => 70,  'text' => 'częściowo pochmurnie'],
        ['max' => 90,  'text' => 'przeważnie pochmurnie'],
        ['max' => INF, 'text' => 'pochmurnie'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'sucho'],
        ['max' => 1.0, 'text' => 'lekkie'],
        ['max' => 5.0, 'text' => 'umiarkowane'],
        ['max' => INF, 'text' => 'silne'],
    ],
];
