<?php

namespace App\Filament\Resources\ProduksiRotaries\Pages;

use App\Filament\Resources\ProduksiRotaries\ProduksiRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiRotary extends EditRecord
{
    protected static string $resource = ProduksiRotaryResource::class;

    protected function beforeSave(): void
    {
        $exists = \App\Models\ProduksiRotary::where('tgl_produksi', $this->data['tgl_produksi'])
            ->where('id_mesin', $this->data['id_mesin'])
            ->where('id', '!=', $this->getRecord()->id)
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.id_mesin' => 'Gagal memperbarui! Kombinasi tanggal dan mesin ini sudah ada di data lain.',
            ]);
        }
    }


    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
