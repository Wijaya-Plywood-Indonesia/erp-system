<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Pages;

use App\Filament\Resources\ReferensiHargaProduksis\ReferensiHargaProduksiResource;
use App\Models\ReferensiHargaProduksi;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class CreateReferensiHargaProduksi extends CreateRecord
{
    protected static string $resource = ReferensiHargaProduksiResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordCreation(array $data): Model
    {
        try {
            return parent::handleRecordCreation($data);
        } catch (UniqueConstraintViolationException $e) {
            Notification::make()
                ->title('Data sudah ada')
                ->body('Kombinasi Jenis Kayu, Ukuran, Jenis Barang, dan KW yang sama sudah terdaftar. Silakan periksa kembali data Anda.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();

            return new ReferensiHargaProduksi; // satisfy intelephense — never reached
        }
    }
}
