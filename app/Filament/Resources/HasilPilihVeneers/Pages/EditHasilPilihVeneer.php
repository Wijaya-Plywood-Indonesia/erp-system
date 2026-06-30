<?php

namespace App\Filament\Resources\HasilPilihVeneers\Pages;

use App\Filament\Resources\HasilPilihVeneers\HasilPilihVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilPilihVeneer extends EditRecord
{
    protected static string $resource = HasilPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
