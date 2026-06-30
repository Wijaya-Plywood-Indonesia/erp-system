<?php

namespace App\Filament\Resources\ValidasiPotJeleks\Pages;

use App\Filament\Resources\ValidasiPotJeleks\ValidasiPotJelekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiPotJeleks extends ListRecords
{
    protected static string $resource = ValidasiPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
