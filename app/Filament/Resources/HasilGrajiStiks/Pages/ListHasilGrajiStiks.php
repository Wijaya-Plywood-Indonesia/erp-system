<?php

namespace App\Filament\Resources\HasilGrajiStiks\Pages;

use App\Filament\Resources\HasilGrajiStiks\HasilGrajiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListHasilGrajiStiks extends ListRecords
{
    protected static string $resource = HasilGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
