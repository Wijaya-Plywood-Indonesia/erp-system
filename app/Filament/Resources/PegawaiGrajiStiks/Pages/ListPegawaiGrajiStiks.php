<?php

namespace App\Filament\Resources\PegawaiGrajiStiks\Pages;

use App\Filament\Resources\PegawaiGrajiStiks\PegawaiGrajiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiGrajiStiks extends ListRecords
{
    protected static string $resource = PegawaiGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
