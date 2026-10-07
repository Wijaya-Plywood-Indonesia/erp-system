<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Override brand (untuk tes lokal)
    |--------------------------------------------------------------------------
    | Isi 'wijaya' atau 'wahana' di .env (BRAND_OVERRIDE) untuk memaksa brand.
    | Kosongkan di server produksi supaya brand ditentukan dari domain.
    */
    'override' => env('BRAND_OVERRIDE'),

    /*
    |--------------------------------------------------------------------------
    | Domain yang dianggap Wijaya. Domain lain otomatis Wahana.
    |--------------------------------------------------------------------------
    */
    'wijaya_hosts' => [
        'kayu.wijayaplywoods.com',
        'prarelease.wijayaplywoods.com',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data per brand
    |--------------------------------------------------------------------------
    */
    'brands' => [
        'wijaya' => [
            'name'                => 'Wijaya',
            'company'             => 'PT Wijaya Plywood Indonesia',
            'background'          => 'images/login-bg.webp',
            'website'             => 'https://wijayaplywoods.com',
            'login_logo_height'   => '6rem',
            'login_logo_height_mobile' => '5rem',
            'landing_logo_height' => '7rem',
        ],
        'wahana' => [
            'name'                => 'Wahana',
            'company'             => 'PT Wahana Plywood Indah',
            'background'          => 'images/login-bg.webp',
            'website'             => '', // isi kalau Wahana punya website company profile
            'login_logo_height'   => '5rem',
            'login_logo_height_mobile' => '4.5rem',
            'landing_logo_height' => '5.5rem',
        ],
    ],

];