<?php

namespace App\Filament\Resources\DetailPegawaiHps\Pages;

use App\Filament\Resources\DetailPegawaiHps\DetailPegawaiHpResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailPegawaiHps extends ListRecords
{
    protected static string $resource = DetailPegawaiHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
