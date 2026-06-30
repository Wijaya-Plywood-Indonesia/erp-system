<?php

namespace App\Filament\Resources\TriplekHasilHps\Pages;

use App\Filament\Resources\TriplekHasilHps\TriplekHasilHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTriplekHasilHps extends ListRecords
{
    protected static string $resource = TriplekHasilHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
