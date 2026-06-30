<?php

namespace App\Filament\Resources\VeneerBahanHps\Pages;

use App\Filament\Resources\VeneerBahanHps\VeneerBahanHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVeneerBahanHps extends ListRecords
{
    protected static string $resource = VeneerBahanHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
