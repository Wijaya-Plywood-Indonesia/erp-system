<?php

namespace App\Filament\Resources\ProduksiTembelTripleks\Pages;

use App\Filament\Resources\ProduksiTembelTripleks\ProduksiTembelTriplekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiTembelTripleks extends ListRecords
{
    protected static string $resource = ProduksiTembelTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
