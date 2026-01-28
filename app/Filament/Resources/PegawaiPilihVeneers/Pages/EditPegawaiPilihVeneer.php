<?php

namespace App\Filament\Resources\PegawaiPilihVeneers\Pages;

use App\Filament\Resources\PegawaiPilihVeneers\PegawaiPilihVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiPilihVeneer extends EditRecord
{
    protected static string $resource = PegawaiPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
