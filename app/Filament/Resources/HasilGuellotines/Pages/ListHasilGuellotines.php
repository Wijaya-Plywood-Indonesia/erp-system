<?php

namespace App\Filament\Resources\HasilGuellotines\Pages;

use App\Filament\Resources\HasilGuellotines\HasilGuellotineResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilGuellotines extends ListRecords
{
    protected static string $resource = HasilGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
