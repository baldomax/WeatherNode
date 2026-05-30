<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'helado'],
        ['max' => 5,  'text' => 'frío'],
        ['max' => 12, 'text' => 'fresco'],
        ['max' => 18, 'text' => 'templado'],
        ['max' => 25, 'text' => 'cálido'],
        ['max' => INF,'text' => 'caluroso'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'calma a viento ligero'],
        ['max' => 5.5,  'text' => 'una brisa suave'],
        ['max' => 10.8, 'text' => 'una brisa moderada'],
        ['max' => 17.2, 'text' => 'una brisa fuerte'],
        ['max' => 24.5, 'text' => 'viento de temporal'],
        ['max' => INF,  'text' => 'viento de fuerza de tormenta'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'muy improbable'],
        ['max' => 25,  'text' => 'una pequeña posibilidad'],
        ['max' => 45,  'text' => 'una posibilidad'],
        ['max' => 65,  'text' => 'probable'],
        ['max' => 85,  'text' => 'muy probable'],
        ['max' => INF, 'text' => 'casi seguro'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'cielo despejado'],
        ['max' => 35,  'text' => 'mayormente soleado'],
        ['max' => 70,  'text' => 'parcialmente nublado'],
        ['max' => 90,  'text' => 'mayormente nublado'],
        ['max' => INF, 'text' => 'nublado'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'seco'],
        ['max' => 1.0, 'text' => 'ligero'],
        ['max' => 5.0, 'text' => 'moderado'],
        ['max' => INF, 'text' => 'fuerte'],
    ],
];
