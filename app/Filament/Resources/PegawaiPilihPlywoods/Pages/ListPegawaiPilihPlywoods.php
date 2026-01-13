<?php

namespace App\Filament\Resources\PegawaiPilihPlywoods\Pages;

use App\Filament\Resources\PegawaiPilihPlywoods\PegawaiPilihPlywoodResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiPilihPlywoods extends ListRecords
{
    protected static string $resource = PegawaiPilihPlywoodResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
