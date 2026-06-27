<?php

namespace App\Filament\Resources\ReferensiHargaProduksis\Pages;

use App\Filament\Resources\ReferensiHargaProduksis\ReferensiHargaProduksiResource;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;

class EditReferensiHargaProduksi extends EditRecord
{
    protected static string $resource = ReferensiHargaProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        try {
            return parent::handleRecordUpdate($record, $data);
        } catch (UniqueConstraintViolationException $e) {
            Notification::make()
                ->title('Data sudah ada')
                ->body('Kombinasi Jenis Kayu, Ukuran, Jenis Barang, dan KW yang sama sudah terdaftar. Silakan periksa kembali data Anda.')
                ->danger()
                ->persistent()
                ->send();

            $this->halt();

            return $record; // satisfy intelephense — halt() stops execution via Livewire
        }
    }
}
