<?php

namespace App\Filament\Resources\ProduksiStiks\Pages;

use App\Filament\Resources\ProduksiStiks\ProduksiStikResource;
use App\Models\ProduksiStik;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateProduksiStik extends CreateRecord
{
    protected static string $resource = ProduksiStikResource::class;

    /**
     * Memanfaatkan ValidationException untuk memunculkan teks merah di bawah input
     */
    protected function beforeCreate(): void
    {
        $tanggal = $this->data['tanggal_produksi'] ?? null;

        if ($tanggal) {
            $exists = ProduksiStik::whereDate('tanggal_produksi', $tanggal)->exists();

            if ($exists) {
                // Ini akan memunculkan teks kecil merah tepat di bawah kolom tanggal_produksi
                throw ValidationException::withMessages([
                    'data.tanggal_produksi' => 'Data untuk tanggal ini sudah ada. Silakan pilih tanggal lain.',
                ]);
            }
        }
    }
}