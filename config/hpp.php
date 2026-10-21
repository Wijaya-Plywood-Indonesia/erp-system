<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Alur HPP Veneer Basah baru
    |--------------------------------------------------------------------------
    | false (bawaan) = alur LAMA: HPP diisi saat validasi hasil rotary.
    | true           = alur BARU: HPP diisi saat palet diterima gudang dan
    |                  difinalkan saat "Selesaikan Lahan" (sumber: Persentase Kayu).
    |
    | Nyalakan lewat .env:  HPP_VENEER_BASAH_BARU=true
    | (setelah mengubah .env jalankan: php artisan config:clear)
    */
    'veneer_basah_baru' => env('HPP_VENEER_BASAH_BARU', false),

];