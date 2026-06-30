<?php

namespace App\Filament\Resources\DetailKayuMasuks\Pages;

use App\Filament\Resources\DetailKayuMasuks\DetailKayuMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailKayuMasuks extends ListRecords
{
    protected static string $resource = DetailKayuMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
