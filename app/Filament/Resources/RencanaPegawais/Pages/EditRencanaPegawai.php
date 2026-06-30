<?php

namespace App\Filament\Resources\RencanaPegawais\Pages;

use App\Filament\Resources\RencanaPegawais\RencanaPegawaiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRencanaPegawai extends EditRecord
{
    protected static string $resource = RencanaPegawaiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
