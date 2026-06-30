<?php

namespace App\Filament\Resources\BahanPenolongHps\Pages;

use App\Filament\Resources\BahanPenolongHps\BahanPenolongHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanPenolongHps extends ListRecords
{
    protected static string $resource = BahanPenolongHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
