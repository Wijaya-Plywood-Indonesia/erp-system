<?php

namespace App\Filament\Resources\ModalGrajiStiks\Pages;

use App\Filament\Resources\ModalGrajiStiks\ModalGrajiStikResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModalGrajiStiks extends ListRecords
{
    protected static string $resource = ModalGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
