<?php

namespace App\Filament\Resources\ProduksiRepairs\Pages;

use App\Filament\Resources\ProduksiRepairs\ProduksiRepairResource;
use App\Filament\Resources\ProduksiRepairs\Widgets\RekapProduksiRepair;
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

    protected function getHeaderWidgets(): array
    {
        return [RekapProduksiRepair::class];
    }
}
