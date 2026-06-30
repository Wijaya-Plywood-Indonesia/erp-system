<?php

namespace App\Filament\Resources\ModalSandings\Pages;

use App\Filament\Resources\ModalSandings\ModalSandingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModalSandings extends ListRecords
{
    protected static string $resource = ModalSandingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
