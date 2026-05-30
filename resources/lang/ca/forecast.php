<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'gelat'],
        ['max' => 5,  'text' => 'fred'],
        ['max' => 12, 'text' => 'fresc'],
        ['max' => 18, 'text' => 'templat'],
        ['max' => 25, 'text' => 'càlid'],
        ['max' => INF,'text' => 'calent'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'calma a vent lleuger'],
        ['max' => 5.5,  'text' => 'una brisa suau'],
        ['max' => 10.8, 'text' => 'una brisa moderada'],
        ['max' => 17.2, 'text' => 'una brisa forta'],
        ['max' => 24.5, 'text' => 'vent de temporal'],
        ['max' => INF,  'text' => 'vent de força de tempesta'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'molt improbable'],
        ['max' => 25,  'text' => 'una petita possibilitat'],
        ['max' => 45,  'text' => 'una possibilitat'],
        ['max' => 65,  'text' => 'probable'],
        ['max' => 85,  'text' => 'molt probable'],
        ['max' => INF, 'text' => 'gairebé segur'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'cel despejat'],
        ['max' => 35,  'text' => 'majoritàriament assolellat'],
        ['max' => 70,  'text' => 'parcialment ennuvolat'],
        ['max' => 90,  'text' => 'majoritàriament ennuvolat'],
        ['max' => INF, 'text' => 'ennuvolat'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'sec'],
        ['max' => 1.0, 'text' => 'lleuger'],
        ['max' => 5.0, 'text' => 'moderat'],
        ['max' => INF, 'text' => 'fort'],
    ],
];
