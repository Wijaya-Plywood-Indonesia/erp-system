<?php

namespace App\Filament\Resources\PegawaiGrajiTripleks\Pages;

use App\Filament\Resources\PegawaiGrajiTripleks\PegawaiGrajiTriplekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiGrajiTripleks extends ListRecords
{
    protected static string $resource = PegawaiGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
