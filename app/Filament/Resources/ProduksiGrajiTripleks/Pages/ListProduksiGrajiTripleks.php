<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Pages;

use App\Filament\Resources\ProduksiGrajiTripleks\ProduksiGrajiTriplekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiGrajiTripleks extends ListRecords
{
    protected static string $resource = ProduksiGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
