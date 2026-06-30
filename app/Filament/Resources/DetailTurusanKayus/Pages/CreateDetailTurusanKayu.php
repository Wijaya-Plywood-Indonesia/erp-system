<?php

namespace App\Filament\Resources\DetailTurusanKayus\Pages;

use App\Filament\Resources\DetailTurusanKayus\DetailTurusanKayuResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreateDetailTurusanKayu extends CreateRecord
{
    protected static string $resource = DetailTurusanKayuResource::class;
    protected function getFormActions(): array
    {
        return [
            Action::make('create')
                ->label('Simpan & Tambah Lagi')
                ->submit('create')
                ->color('warning')
                ->action(function () {
                    // Simpan data seperti biasa
                    $this->create();

                    // Langsung redirect ke halaman create baru
                    $this->redirect(static::getResource()::getUrl('create'));
                }),

            Action::make('cancel')
                ->label('Batal')
                ->color('gray')
                ->url(static::getResource()::getUrl('index')),
        ];
    }
}
