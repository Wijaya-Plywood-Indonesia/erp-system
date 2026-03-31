<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Pages;

use App\Filament\Resources\PenggunaanLahanRotaries\PenggunaanLahanRotaryResource;
use App\Services\HppAverageService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPenggunaanLahanRotary extends EditRecord
{
    protected static string $resource = PenggunaanLahanRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        $record = $this->record;

        // Logika yang sama: jika jumlah batang tidak nol
        if ($record->jumlah_batang != 0) {
            app(HppAverageService::class)->prosesKeluarRotary(
                lahanId: $record->id_lahan,
                jenisKayuId: $record->id_jenis_kayu,
                referensi: $record
            );
        }
    }
}
