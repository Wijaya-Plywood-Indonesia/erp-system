<?php

namespace App\Filament\Resources\ProduksiSandings\Pages;

use App\Filament\Resources\ProduksiSandings\ProduksiSandingResource;
use App\Filament\Resources\ProduksiSandings\Widgets\RekapProduksiSanding;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiSandings extends ListRecords
{
    protected static string $resource = ProduksiSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [RekapProduksiSanding::class];
    }
}
