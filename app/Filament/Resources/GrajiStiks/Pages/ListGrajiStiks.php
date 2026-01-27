<?php

namespace App\Filament\Resources\GrajiStiks\Pages;

use App\Filament\Resources\GrajiStiks\GrajiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGrajiStiks extends ListRecords
{
    protected static string $resource = GrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
