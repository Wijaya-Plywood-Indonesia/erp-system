<?php

namespace App\Filament\Resources\ValidasiPressDryers\Pages;

use App\Filament\Resources\ValidasiPressDryers\ValidasiPressDryerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListValidasiPressDryers extends ListRecords
{
    protected static string $resource = ValidasiPressDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
