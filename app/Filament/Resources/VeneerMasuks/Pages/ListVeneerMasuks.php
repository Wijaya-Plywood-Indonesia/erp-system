<?php

namespace App\Filament\Resources\VeneerMasuks\Pages;

use App\Filament\Resources\VeneerMasuks\VeneerMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVeneerMasuks extends ListRecords
{
    protected static string $resource = VeneerMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
