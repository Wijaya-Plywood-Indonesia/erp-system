<?php

namespace App\Filament\Resources\ValidasiGrajiTripleks\Pages;

use App\Filament\Resources\ValidasiGrajiTripleks\ValidasiGrajiTriplekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiGrajiTriplek extends EditRecord
{
    protected static string $resource = ValidasiGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
