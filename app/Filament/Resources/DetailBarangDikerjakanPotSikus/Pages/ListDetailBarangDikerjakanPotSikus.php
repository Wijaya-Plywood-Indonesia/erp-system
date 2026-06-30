<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotSikus\Pages;

use App\Filament\Resources\DetailBarangDikerjakanPotSikus\DetailBarangDikerjakanPotSikuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailBarangDikerjakanPotSikus extends ListRecords
{
    protected static string $resource = DetailBarangDikerjakanPotSikuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
