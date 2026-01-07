<?php

namespace App\Filament\Resources\ProduksiPilihPlywoods\Pages;

use App\Filament\Resources\ProduksiPilihPlywoods\ProduksiPilihPlywoodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiPilihPlywoods extends ListRecords
{
    protected static string $resource = ProduksiPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
