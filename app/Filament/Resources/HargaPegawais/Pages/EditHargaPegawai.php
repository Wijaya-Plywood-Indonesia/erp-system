<?php

namespace App\Filament\Resources\HargaPegawais\Pages;

use App\Filament\Resources\HargaPegawais\HargaPegawaiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHargaPegawai extends EditRecord
{
    protected static string $resource = HargaPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
