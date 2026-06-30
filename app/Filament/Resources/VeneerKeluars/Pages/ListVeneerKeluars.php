<?php

namespace App\Filament\Resources\VeneerKeluars\Pages;

use App\Filament\Resources\VeneerKeluars\VeneerKeluarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVeneerKeluars extends ListRecords
{
    protected static string $resource = VeneerKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
