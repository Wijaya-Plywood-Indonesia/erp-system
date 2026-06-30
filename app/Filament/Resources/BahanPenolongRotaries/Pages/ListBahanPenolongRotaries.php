<?php

namespace App\Filament\Resources\BahanPenolongRotaries\Pages;

use App\Filament\Resources\BahanPenolongRotaries\BahanPenolongRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBahanPenolongRotaries extends ListRecords
{
    protected static string $resource = BahanPenolongRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
