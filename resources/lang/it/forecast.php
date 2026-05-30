<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'gelido'],
        ['max' => 5,  'text' => 'freddo'],
        ['max' => 12, 'text' => 'fresco'],
        ['max' => 18, 'text' => 'mite'],
        ['max' => 25, 'text' => 'caldo'],
        ['max' => INF,'text' => 'molto caldo'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'calma a vento leggero'],
        ['max' => 5.5,  'text' => 'una brezza leggera'],
        ['max' => 10.8, 'text' => 'una brezza moderata'],
        ['max' => 17.2, 'text' => 'una brezza forte'],
        ['max' => 24.5, 'text' => 'vento di burrasca'],
        ['max' => INF,  'text' => 'vento di tempesta'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'molto improbabile'],
        ['max' => 25,  'text' => 'una piccola possibilità'],
        ['max' => 45,  'text' => 'una possibilità'],
        ['max' => 65,  'text' => 'probabile'],
        ['max' => 85,  'text' => 'molto probabile'],
        ['max' => INF, 'text' => 'quasi certo'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'cielo sereno'],
        ['max' => 35,  'text' => 'prevalentemente soleggiato'],
        ['max' => 70,  'text' => 'parzialmente nuvoloso'],
        ['max' => 90,  'text' => 'prevalentemente nuvoloso'],
        ['max' => INF, 'text' => 'coperto'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'secco'],
        ['max' => 1.0, 'text' => 'leggero'],
        ['max' => 5.0, 'text' => 'moderato'],
        ['max' => INF, 'text' => 'forte'],
    ],
];
