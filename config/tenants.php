<?php

use App\Support\Sorting\NumericKodeSorting;
use App\Support\Sorting\PriorityGroupSorting;

return [
    'sites' => [
        'site_a' => [
            'hosts' => [
                'kayu.wijayaplywoods.com',
                'prarelease.wijayaplywoods.com',
            ],
            'sorting_strategy' => NumericKodeSorting::class,
        ],

        'site_b' => [
            'hosts' => [
                'wahana.wijayaplywoods.com',
                'prarelease-wahana.wijayaplywoods.com',
                // tambahkan staging domain B di sini kalau ada
            ],
            'sorting_strategy' => PriorityGroupSorting::class,
        ],
    ],

    // fallback kalau host yang request tidak ketemu di daftar manapun (misal saat dev di localhost)
    'default_sorting_strategy' => NumericKodeSorting::class,
];
