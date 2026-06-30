<?php

namespace App\Filament\Resources\ValidasiPilihPlywoods\Pages;

use App\Filament\Resources\ValidasiPilihPlywoods\ValidasiPilihPlywoodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiPilihPlywoods extends ListRecords
{
    protected static string $resource = ValidasiPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
