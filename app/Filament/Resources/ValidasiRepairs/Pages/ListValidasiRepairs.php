<?php

namespace App\Filament\Resources\ValidasiRepairs\Pages;

use App\Filament\Resources\ValidasiRepairs\ValidasiRepairResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiRepairs extends ListRecords
{
    protected static string $resource = ValidasiRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
