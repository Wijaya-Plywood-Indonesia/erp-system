<?php

namespace App\Filament\Resources\ProduksiRotaries\Pages;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduksiRotary extends CreateRecord
{
    protected static string $resource = ProduksiRotaryResource::class;

    protected function beforeCreate(): void
    {
        $exists = \App\Models\ProduksiRotary::where('tgl_produksi', $this->data['tgl_produksi'])
            ->where('id_mesin', $this->data['id_mesin'])
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.id_mesin' => 'Duplikasi terdeteksi! Mesin ini sudah digunakan pada tanggal yang dipilih.',
            ]);
        }
    }
}
