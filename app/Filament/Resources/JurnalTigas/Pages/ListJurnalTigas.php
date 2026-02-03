<?php

namespace App\Filament\Resources\JurnalTigas\Pages;

use App\Filament\Resources\JurnalTigas\JurnalTigaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJurnalTigas extends ListRecords
{
    protected static string $resource = JurnalTigaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
