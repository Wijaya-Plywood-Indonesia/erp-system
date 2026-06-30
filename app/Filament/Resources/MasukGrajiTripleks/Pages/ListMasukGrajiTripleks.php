<?php

namespace App\Filament\Resources\MasukGrajiTripleks\Pages;

use App\Filament\Resources\MasukGrajiTripleks\MasukGrajiTriplekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMasukGrajiTripleks extends ListRecords
{
    protected static string $resource = MasukGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
