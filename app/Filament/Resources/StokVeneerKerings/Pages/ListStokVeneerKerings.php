<?php

namespace App\Filament\Resources\StokVeneerKerings\Pages;

use App\Filament\Resources\StokVeneerKerings\StokVeneerKeringResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListStokVeneerKerings extends ListRecords
{
    protected static string $resource = StokVeneerKeringResource::class;

    protected function getHeaderActions(): array
    {
        return [

        ];
    }
}
