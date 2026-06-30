<?php

namespace App\Filament\Resources\DetailBarangDikerjakanPotJeleks\Pages;

use App\Filament\Resources\DetailBarangDikerjakanPotJeleks\DetailBarangDikerjakanPotJelekResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailBarangDikerjakanPotJeleks extends ListRecords
{
    protected static string $resource = DetailBarangDikerjakanPotJelekResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
