<?php

namespace App\Filament\Resources\ValidasiPotSikus\Pages;

use App\Filament\Resources\ValidasiPotSikus\ValidasiPotSikuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiPotSikus extends ListRecords
{
    protected static string $resource = ValidasiPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
