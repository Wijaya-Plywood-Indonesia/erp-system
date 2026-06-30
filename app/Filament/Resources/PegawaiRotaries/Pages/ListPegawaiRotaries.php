<?php

namespace App\Filament\Resources\PegawaiRotaries\Pages;

use App\Filament\Resources\PegawaiRotaries\PegawaiRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPegawaiRotaries extends ListRecords
{
    protected static string $resource = PegawaiRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
