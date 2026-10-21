<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\Pages;

use App\Filament\Resources\ProduksiPilihVeneers\ProduksiPilihVeneerResource;
use App\Filament\Resources\ProduksiPilihVeneers\Widgets\RekapProduksiPilihVeneer;
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

    protected function getHeaderWidgets(): array
    {
        return [RekapProduksiPilihVeneer::class];
    }
}
