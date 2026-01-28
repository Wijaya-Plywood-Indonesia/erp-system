<?php

namespace App\Filament\Resources\ModalPilihVeneers\Pages;

use App\Filament\Resources\ModalPilihVeneers\ModalPilihVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModalPilihVeneer extends EditRecord
{
    protected static string $resource = ModalPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
