<?php

namespace App\Filament\Resources\BahanProduksis\Pages;

use App\Filament\Resources\BahanProduksis\BahanProduksiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanProduksis extends ListRecords
{
    protected static string $resource = BahanProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
