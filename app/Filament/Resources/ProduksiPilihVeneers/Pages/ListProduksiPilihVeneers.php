<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\Pages;

use App\Filament\Resources\ProduksiPilihVeneers\ProduksiPilihVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiPilihVeneers extends ListRecords
{
    protected static string $resource = ProduksiPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
