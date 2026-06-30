<?php

namespace App\Filament\Resources\ProduksiRepairs\Pages;

use App\Filament\Resources\ProduksiRepairs\ProduksiRepairResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProduksiRepairs extends ListRecords
{
    protected static string $resource = ProduksiRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
