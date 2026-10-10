<?php

namespace App\Filament\Resources\ProduksiHotPresses\Pages;

use App\Filament\Resources\ProduksiHotPresses\ProduksiHotPressResource;
use App\Filament\Resources\ProduksiHotPresses\Widgets\RekapProduksiHotPress;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiHotPresses extends ListRecords
{
    protected static string $resource = ProduksiHotPressResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [RekapProduksiHotPress::class];
    }
}
