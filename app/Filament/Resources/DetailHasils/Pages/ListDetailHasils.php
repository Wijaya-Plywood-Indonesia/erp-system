<?php

namespace App\Filament\Resources\DetailHasils\Pages;

use App\Filament\Resources\DetailHasils\DetailHasilResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailHasils extends ListRecords
{
    protected static string $resource = DetailHasilResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
