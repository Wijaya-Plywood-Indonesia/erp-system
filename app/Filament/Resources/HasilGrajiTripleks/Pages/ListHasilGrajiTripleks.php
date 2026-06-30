<?php

namespace App\Filament\Resources\HasilGrajiTripleks\Pages;

use App\Filament\Resources\HasilGrajiTripleks\HasilGrajiTriplekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilGrajiTripleks extends ListRecords
{
    protected static string $resource = HasilGrajiTriplekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
