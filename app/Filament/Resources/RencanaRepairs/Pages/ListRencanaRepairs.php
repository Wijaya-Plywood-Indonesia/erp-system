<?php

namespace App\Filament\Resources\RencanaRepairs\Pages;

use App\Filament\Resources\RencanaRepairs\RencanaRepairResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRencanaRepairs extends ListRecords
{
    protected static string $resource = RencanaRepairResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
