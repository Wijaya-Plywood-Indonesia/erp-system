<?php

namespace App\Filament\Resources\DetailBarangDikerjakans\Pages;

use App\Filament\Resources\DetailBarangDikerjakans\DetailBarangDikerjakanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailBarangDikerjakans extends ListRecords
{
    protected static string $resource = DetailBarangDikerjakanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
