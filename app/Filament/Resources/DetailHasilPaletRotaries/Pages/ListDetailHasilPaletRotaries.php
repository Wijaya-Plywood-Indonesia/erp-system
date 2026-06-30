<?php

namespace App\Filament\Resources\DetailHasilPaletRotaries\Pages;

use App\Filament\Resources\DetailHasilPaletRotaries\DetailHasilPaletRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailHasilPaletRotaries extends ListRecords
{
    protected static string $resource = DetailHasilPaletRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
