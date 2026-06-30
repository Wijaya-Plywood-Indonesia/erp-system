<?php

namespace App\Filament\Resources\HargaVeneers\Pages;

use App\Filament\Resources\HargaVeneers\HargaVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHargaVeneers extends ListRecords
{
    protected static string $resource = HargaVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
