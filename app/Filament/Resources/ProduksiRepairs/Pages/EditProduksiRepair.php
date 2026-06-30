<?php

namespace App\Filament\Resources\ProduksiRepairs\Pages;

use App\Filament\Resources\ProduksiRepairs\ProduksiRepairResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiRepair extends EditRecord
{
    protected static string $resource = ProduksiRepairResource::class;

    protected function beforeSave(): void
    {
        $exists = \App\Models\ProduksiRepair::whereDate('tanggal', $this->data['tanggal'])
            ->where('id', '!=', $this->getRecord()->id)
            ->exists();

        if ($exists) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'data.tanggal' => 'Gagal mengubah! Tanggal ini sudah digunakan oleh laporan lain.',
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
