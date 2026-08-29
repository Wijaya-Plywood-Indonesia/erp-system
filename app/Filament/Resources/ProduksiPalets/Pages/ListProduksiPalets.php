<?php

namespace App\Filament\Resources\ProduksiPalets\Pages;

use App\Filament\Resources\ProduksiPalets\ProduksiPaletResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiPalets extends ListRecords
{
    protected static string $resource = ProduksiPaletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
