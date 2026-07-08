<?php

namespace App\Filament\Resources\ProduksiTerimaGudangSatus\Pages;

use App\Filament\Resources\ProduksiTerimaGudangSatus\ProduksiTerimaGudangSatuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiTerimaGudangSatus extends ListRecords
{
    protected static string $resource = ProduksiTerimaGudangSatuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
