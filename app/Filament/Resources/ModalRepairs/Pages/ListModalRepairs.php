<?php

namespace App\Filament\Resources\ModalRepairs\Pages;

use App\Filament\Resources\ModalRepairs\ModalRepairResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListModalRepairs extends ListRecords
{
    protected static string $resource = ModalRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
