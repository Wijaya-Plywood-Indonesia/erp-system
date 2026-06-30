<?php

namespace App\Filament\Resources\ValidasiGrajiStiks\Pages;

use App\Filament\Resources\ValidasiGrajiStiks\ValidasiGrajiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiGrajiStiks extends ListRecords
{
    protected static string $resource = ValidasiGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
