<?php

namespace App\Filament\Resources\ValidasiHasilRotaries\Pages;

use App\Filament\Resources\ValidasiHasilRotaries\ValidasiHasilRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiHasilRotaries extends ListRecords
{
    protected static string $resource = ValidasiHasilRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
