<?php

namespace App\Filament\Resources\ProduksiNyusups\Pages;

use App\Filament\Resources\ProduksiNyusups\ProduksiNyusupResource;
use App\Filament\Resources\ProduksiNyusups\Widgets\RekapProduksiNyusup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiNyusups extends ListRecords
{
    protected static string $resource = ProduksiNyusupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            RekapProduksiNyusup::class,
        ];
    }
}
