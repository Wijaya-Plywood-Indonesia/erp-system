<?php

namespace App\Filament\Resources\ProduksiGrajiTripleks\Pages;

use App\Filament\Resources\ProduksiGrajiTripleks\ProduksiGrajiTriplekResource;
use App\Filament\Resources\ProduksiGrajiTripleks\Widgets\RekapProduksiGraji;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiGrajiTripleks extends ListRecords
{
    protected static string $resource = ProduksiGrajiTriplekResource::class;

    protected function getHeaderWidgets(): array
    {
        return [
            RekapProduksiGraji::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
