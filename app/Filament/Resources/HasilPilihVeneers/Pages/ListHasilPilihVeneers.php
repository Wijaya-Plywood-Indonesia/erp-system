<?php

namespace App\Filament\Resources\HasilPilihVeneers\Pages;

use App\Filament\Resources\HasilPilihVeneers\HasilPilihVeneerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilPilihVeneers extends ListRecords
{
    protected static string $resource = HasilPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
