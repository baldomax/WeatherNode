<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'gelado'],
        ['max' => 5,  'text' => 'frio'],
        ['max' => 12, 'text' => 'fresco'],
        ['max' => 18, 'text' => 'ameno'],
        ['max' => 25, 'text' => 'quente'],
        ['max' => INF,'text' => 'muito quente'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'calmo a vento leve'],
        ['max' => 5.5,  'text' => 'uma brisa suave'],
        ['max' => 10.8, 'text' => 'uma brisa moderada'],
        ['max' => 17.2, 'text' => 'uma brisa forte'],
        ['max' => 24.5, 'text' => 'vento de temporal'],
        ['max' => INF,  'text' => 'vento de força de tempestade'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'muito improvável'],
        ['max' => 25,  'text' => 'uma pequena chance'],
        ['max' => 45,  'text' => 'uma chance'],
        ['max' => 65,  'text' => 'provável'],
        ['max' => 85,  'text' => 'muito provável'],
        ['max' => INF, 'text' => 'quase certo'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'céu limpo'],
        ['max' => 35,  'text' => 'predominantemente ensolarado'],
        ['max' => 70,  'text' => 'parcialmente nublado'],
        ['max' => 90,  'text' => 'predominantemente nublado'],
        ['max' => INF, 'text' => 'nublado'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'seco'],
        ['max' => 1.0, 'text' => 'leve'],
        ['max' => 5.0, 'text' => 'moderado'],
        ['max' => INF, 'text' => 'forte'],
    ],
];
