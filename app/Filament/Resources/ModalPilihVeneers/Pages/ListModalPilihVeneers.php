<?php

namespace App\Filament\Resources\ModalPilihVeneers\Pages;

use App\Filament\Resources\ModalPilihVeneers\ModalPilihVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModalPilihVeneers extends ListRecords
{
    protected static string $resource = ModalPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
