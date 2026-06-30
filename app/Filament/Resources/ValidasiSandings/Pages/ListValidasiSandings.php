<?php

namespace App\Filament\Resources\ValidasiSandings\Pages;

use App\Filament\Resources\ValidasiSandings\ValidasiSandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiSandings extends ListRecords
{
    protected static string $resource = ValidasiSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
