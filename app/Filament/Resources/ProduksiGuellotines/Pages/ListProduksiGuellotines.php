<?php

namespace App\Filament\Resources\ProduksiGuellotines\Pages;

use App\Filament\Resources\ProduksiGuellotines\ProduksiGuellotineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiGuellotines extends ListRecords
{
    protected static string $resource = ProduksiGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
