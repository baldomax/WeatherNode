<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'glacial'],
        ['max' => 5,  'text' => 'froid'],
        ['max' => 12, 'text' => 'frais'],
        ['max' => 18, 'text' => 'doux'],
        ['max' => 25, 'text' => 'chaud'],
        ['max' => INF,'text' => 'très chaud'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'calme à vent léger'],
        ['max' => 5.5,  'text' => 'une brise légère'],
        ['max' => 10.8, 'text' => 'une brise modérée'],
        ['max' => 17.2, 'text' => 'une brise forte'],
        ['max' => 24.5, 'text' => 'vent de tempête'],
        ['max' => INF,  'text' => 'vent de force tempête'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'très improbable'],
        ['max' => 25,  'text' => 'une petite chance'],
        ['max' => 45,  'text' => 'une chance'],
        ['max' => 65,  'text' => 'probable'],
        ['max' => 85,  'text' => 'très probable'],
        ['max' => INF, 'text' => 'presque certain'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'ciel dégagé'],
        ['max' => 35,  'text' => 'généralement ensoleillé'],
        ['max' => 70,  'text' => 'partiellement nuageux'],
        ['max' => 90,  'text' => 'généralement nuageux'],
        ['max' => INF, 'text' => 'couvert'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'sec'],
        ['max' => 1.0, 'text' => 'léger'],
        ['max' => 5.0, 'text' => 'modéré'],
        ['max' => INF, 'text' => 'fort'],
    ],
];
