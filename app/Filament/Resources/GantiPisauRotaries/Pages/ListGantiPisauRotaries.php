<?php

namespace App\Filament\Resources\GantiPisauRotaries\Pages;

use App\Filament\Resources\GantiPisauRotaries\GantiPisauRotaryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGantiPisauRotaries extends ListRecords
{
    protected static string $resource = GantiPisauRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
