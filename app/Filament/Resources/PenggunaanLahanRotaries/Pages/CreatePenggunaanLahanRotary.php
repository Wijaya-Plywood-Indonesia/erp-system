<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Pages;

use App\Filament\Resources\PenggunaanLahanRotaries\PenggunaanLahanRotaryResource;
use App\Services\HppAverageService;
use Filament\Resources\Pages\CreateRecord;

class CreatePenggunaanLahanRotary extends CreateRecord
{
    protected static string $resource = PenggunaanLahanRotaryResource::class;

    protected function afterCreate(): void
    {

        // $record = $this->record;

        // // Jika jumlah batang yang diinput tidak nol (berarti ada kayu yang dikerjakan)
        // if ($record->jumlah_batang != 0) {
        //     // Panggil service untuk reset stok HPP di lahan tersebut
        //     app(HppAverageService::class)->prosesKeluarRotary(
        //         lahanId: $record->id_lahan,
        //         jenisKayuId: $record->id_jenis_kayu,
        //         referensi: $record
        //     );
        // }
    }
}
