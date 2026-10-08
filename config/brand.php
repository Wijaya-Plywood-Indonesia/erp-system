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
    | overlay_start / overlay_end : kegelapan lapisan di atas foto (0 - 1)
    | card_bg / card_bg_mobile    : warna kaca card
    | card_blur / card_saturate   : efek blur kaca
    | bg_position_mobile          : bagian foto yang ditampilkan di HP
    | website                     : kosongkan jika tautan tidak ingin tampil
    */
    'brands' => [
        'wijaya' => [
            'name'                     => 'Wijaya',
            'company'                  => 'PT Wijaya Plywood Indonesia',
            'background'               => 'images/login-bg-wjy.jpeg', // foto tumpukan kayu bulat
            'website'                  => 'https://wijayaplywoods.com',
            'login_logo_height'        => '6rem',
            'login_logo_height_mobile' => '5rem',
            'landing_logo_height'      => '7rem',

            // Foto terang -> overlay dan card dibuat lebih gelap & netral
            'overlay_start'            => '0.82',
            'overlay_end'              => '0.55',
            'card_bg'                  => 'rgba(15, 20, 30, 0.62)',
            'card_bg_mobile'           => 'rgba(15, 20, 30, 0.70)',
            'card_blur'                => '26px',
            'card_saturate'            => '110%',
            'bg_position_mobile'       => '30% center',
        ],
        'wahana' => [
            'name'                     => 'Wahana',
            'company'                  => 'PT Wahana Plywood Indah',
            'background'               => 'images/login-bg.webp', // foto gudang
            'website'                  => 'https://wijayaplywoods.com',
            'login_logo_height'        => '5rem',
            'login_logo_height_mobile' => '4.5rem',
            'landing_logo_height'      => '5.5rem',

            'overlay_start'            => '0.70',
            'overlay_end'              => '0.30',
            'card_bg'                  => 'rgba(15, 23, 42, 0.45)',
            'card_bg_mobile'           => 'rgba(15, 23, 42, 0.58)',
            'card_blur'                => '22px',
            'card_saturate'            => '160%',
            'bg_position_mobile'       => '62% center',
        ],
    ],

];