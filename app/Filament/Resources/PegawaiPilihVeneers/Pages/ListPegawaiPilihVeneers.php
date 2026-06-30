<?php

namespace App\Filament\Resources\PegawaiPilihVeneers\Pages;

use App\Filament\Resources\PegawaiPilihVeneers\PegawaiPilihVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiPilihVeneers extends ListRecords
{
    protected static string $resource = PegawaiPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
