<?php

namespace App\Filament\Resources\ProduksiDempuls\Pages;

use App\Filament\Resources\ProduksiDempuls\ProduksiDempulResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiDempuls extends ListRecords
{
    protected static string $resource = ProduksiDempulResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
