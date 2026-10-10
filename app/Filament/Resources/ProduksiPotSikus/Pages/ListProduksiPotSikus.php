<?php

namespace App\Filament\Resources\ProduksiPotSikus\Pages;

use App\Filament\Resources\ProduksiPotSikus\ProduksiPotSikuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use App\Filament\Resources\ProduksiPotSikus\Widgets\RekapProduksiPotSiku;

class ListProduksiPotSikus extends ListRecords
{
    protected static string $resource = ProduksiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RekapProduksiPotSiku::class,
        ];
    }
}
