<?php

namespace App\Filament\Resources\ValidasiGuellotines\Pages;

use App\Filament\Resources\ValidasiGuellotines\ValidasiGuellotineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiGuellotines extends ListRecords
{
    protected static string $resource = ValidasiGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
