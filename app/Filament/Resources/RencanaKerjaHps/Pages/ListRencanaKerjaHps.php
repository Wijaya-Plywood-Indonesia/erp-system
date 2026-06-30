<?php

namespace App\Filament\Resources\RencanaKerjaHps\Pages;

use App\Filament\Resources\RencanaKerjaHps\RencanaKerjaHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRencanaKerjaHps extends ListRecords
{
    protected static string $resource = RencanaKerjaHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
