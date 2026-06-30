<?php

namespace App\Filament\Resources\PegawaiGrajiTripleks\Pages;

use App\Filament\Resources\PegawaiGrajiTripleks\PegawaiGrajiTriplekResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiGrajiTriplek extends EditRecord
{
    protected static string $resource = PegawaiGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
