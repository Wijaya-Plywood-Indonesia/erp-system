<?php

namespace App\Filament\Resources\BahanPenolongProduksis\Pages;

use App\Filament\Resources\BahanPenolongProduksis\BahanPenolongProduksiResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanPenolongProduksis extends ListRecords
{
    protected static string $resource = BahanPenolongProduksiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
