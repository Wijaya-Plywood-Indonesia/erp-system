<?php

namespace App\Filament\Resources\PegawaiSandings\Pages;

use App\Filament\Resources\PegawaiSandings\PegawaiSandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiSandings extends ListRecords
{
    protected static string $resource = PegawaiSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
