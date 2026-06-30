<?php

namespace App\Filament\Resources\ValidasiGrajiTripleks\Pages;

use App\Filament\Resources\ValidasiGrajiTripleks\ValidasiGrajiTriplekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiGrajiTripleks extends ListRecords
{
    protected static string $resource = ValidasiGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
