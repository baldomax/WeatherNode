<?php

return [
    'temp' => [
        ['max' => 0,  'text' => 'jäätävä'],
        ['max' => 5,  'text' => 'kylmä'],
        ['max' => 12, 'text' => 'viileä'],
        ['max' => 18, 'text' => 'leuto'],
        ['max' => 25, 'text' => 'lämmin'],
        ['max' => INF,'text' => 'kuuma'],
    ],
    'wind' => [
        ['max' => 1.5,  'text' => 'tyyntä kevyeseen tuuleen'],
        ['max' => 5.5,  'text' => 'heikko tuulenvire'],
        ['max' => 10.8, 'text' => 'kohtalainen tuulenvire'],
        ['max' => 17.2, 'text' => 'voimakas tuulenvire'],
        ['max' => 24.5, 'text' => 'myrskyisä tuuli'],
        ['max' => INF,  'text' => 'myrskytuuli'],
    ],
    'precip_prob' => [
        ['max' => 10,  'text' => 'hyvin epätodennäköistä'],
        ['max' => 25,  'text' => 'pieni mahdollisuus'],
        ['max' => 45,  'text' => 'mahdollisuus'],
        ['max' => 65,  'text' => 'todennäköistä'],
        ['max' => 85,  'text' => 'hyvin todennäköistä'],
        ['max' => INF, 'text' => 'melkein varmaa'],
    ],
    'sky' => [
        ['max' => 10,  'text' => 'selkeä taivas'],
        ['max' => 35,  'text' => 'pääosin aurinkoista'],
        ['max' => 70,  'text' => 'puolipilvistä'],
        ['max' => 90,  'text' => 'pääosin pilvistä'],
        ['max' => INF, 'text' => 'pilvistä'],
    ],
    'precip_mm' => [
        ['max' => 0.1, 'text' => 'kuivaa'],
        ['max' => 1.0, 'text' => 'kevyttä'],
        ['max' => 5.0, 'text' => 'kohtalaista'],
        ['max' => INF, 'text' => 'raskasta'],
    ],
];
