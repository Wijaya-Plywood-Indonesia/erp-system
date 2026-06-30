<?php

namespace App\Filament\Resources\ProduksiPressDryers\Pages;

use App\Filament\Resources\ProduksiPressDryers\ProduksiPressDryerResource;
use App\Models\ProduksiPressDryer;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditProduksiPressDryer extends EditRecord
{
    protected static string $resource = ProduksiPressDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function beforeSave(): void
    {
        $tanggal = $this->data['tanggal_produksi'] ?? null;
        $shift = $this->data['shift'] ?? null;
        $recordId = $this->getRecord()->id;

        if ($tanggal && $shift) {
            $exists = ProduksiPressDryer::whereDate('tanggal_produksi', $tanggal)
                ->where('shift', $shift)
                ->where('id', '!=', $recordId)
                ->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'data.shift' => "Gagal memperbarui! Kombinasi tanggal dan Shift {$shift} sudah digunakan di laporan lain.",
                ]);
            }
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}