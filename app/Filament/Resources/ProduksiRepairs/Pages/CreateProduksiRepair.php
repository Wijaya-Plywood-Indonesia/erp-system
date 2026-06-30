<?php

namespace App\Filament\Resources\ProduksiRepairs\Pages;

use App\Filament\Resources\ProduksiRepairs\ProduksiRepairResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProduksiRepair extends CreateRecord
{
    protected static string $resource = ProduksiRepairResource::class;

    protected function beforeCreate(): void
    {
        $exists = \App\Models\ProduksiRepair::whereDate('tanggal', $this->data['tanggal'])->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.tanggal' => 'Gagal simpan! Laporan tanggal ini sudah pernah dibuat.',
            ]);
        }
    }
}
