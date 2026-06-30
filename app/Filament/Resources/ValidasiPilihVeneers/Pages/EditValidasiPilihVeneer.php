<?php

namespace App\Filament\Resources\ValidasiPilihVeneers\Pages;

use App\Filament\Resources\ValidasiPilihVeneers\ValidasiPilihVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiPilihVeneer extends EditRecord
{
    protected static string $resource = ValidasiPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
