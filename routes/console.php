<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Validasi otomatis semua produksi (jam & aturan diatur di ValidasiOtomatisProduksiService)
Schedule::command('produksi:validasi-otomatis')->everyFiveMinutes()->withoutOverlapping();
