<?php

namespace App\Filament\Resources\IsiPaletVeneers\Pages;

use App\Filament\Resources\IsiPaletVeneers\IsiPaletVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListIsiPaletVeneers extends ListRecords
{
    protected static string $resource = IsiPaletVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
