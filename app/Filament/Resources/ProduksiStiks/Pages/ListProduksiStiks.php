<?php

namespace App\Filament\Resources\ProduksiStiks\Pages;

use App\Filament\Resources\ProduksiStiks\ProduksiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiStiks extends ListRecords
{
    protected static string $resource = ProduksiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
