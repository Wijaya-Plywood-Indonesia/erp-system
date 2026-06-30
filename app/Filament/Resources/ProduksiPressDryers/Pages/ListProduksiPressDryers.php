<?php

namespace App\Filament\Resources\ProduksiPressDryers\Pages;

use App\Filament\Resources\ProduksiPressDryers\ProduksiPressDryerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiPressDryers extends ListRecords
{
    protected static string $resource = ProduksiPressDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
