<?php

require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$dates = ['2026-05-21', '2026-05-22'];

foreach ($dates as $date) {
    echo "=== Date: $date ===\n";
    $productions = App\Models\ProduksiPressDryer::whereDate('tanggal_produksi', $date)->get();
    foreach ($productions as $p) {
        echo "Production ID: {$p->id}, Shift: {$p->shift}, Kendala Column: '{$p->kendala}'\n";
        
        $kendalas = $p->kendalaPressDryers;
        echo "Related kendalaPressDryers count: " . $kendalas->count() . "\n";
        foreach ($kendalas as $knd) {
            echo "  - ID: {$knd->id}, Status: {$knd->status}, Durasi: {$knd->durasi_menit} mins, Kendala: '{$knd->kendala}', Waktu Mulai: {$knd->waktu_mulai}, Waktu Selesai: {$knd->waktu_selesai}\n";
        }
    }
    echo "\n";
}
