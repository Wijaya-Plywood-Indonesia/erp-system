<?php

namespace App\Filament\Resources\ValidasiPilihVeneers\Pages;

use App\Filament\Resources\ValidasiPilihVeneers\ValidasiPilihVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiPilihVeneers extends ListRecords
{
    protected static string $resource = ValidasiPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
