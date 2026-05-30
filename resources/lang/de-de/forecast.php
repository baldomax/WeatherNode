<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'eiskalt'],
        ['max' => 5,  'text' => 'kalt'],
        ['max' => 12, 'text' => 'kühl'],
        ['max' => 18, 'text' => 'mild'],
        ['max' => 25, 'text' => 'warm'],
        ['max' => INF,'text' => 'heiß'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'windstill bis leichter Wind'],
        ['max' => 5.5,  'text' => 'eine leichte Brise'],
        ['max' => 10.8, 'text' => 'eine mäßige Brise'],
        ['max' => 17.2, 'text' => 'eine steife Brise'],
        ['max' => 24.5, 'text' => 'stürmischer Wind'],
        ['max' => INF,  'text' => 'Sturmwind'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'sehr unwahrscheinlich'],
        ['max' => 25,  'text' => 'eine kleine Chance'],
        ['max' => 45,  'text' => 'eine Chance'],
        ['max' => 65,  'text' => 'wahrscheinlich'],
        ['max' => 85,  'text' => 'sehr wahrscheinlich'],
        ['max' => INF, 'text' => 'fast sicher'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'klarer Himmel'],
        ['max' => 35,  'text' => 'überwiegend sonnig'],
        ['max' => 70,  'text' => 'teilweise bewölkt'],
        ['max' => 90,  'text' => 'überwiegend bewölkt'],
        ['max' => INF, 'text' => 'bedeckt'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'trocken'],
        ['max' => 1.0, 'text' => 'leicht'],
        ['max' => 5.0, 'text' => 'mäßig'],
        ['max' => INF, 'text' => 'stark'],
    ],
];
