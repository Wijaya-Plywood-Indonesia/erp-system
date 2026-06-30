<?php

namespace App\Filament\Resources\DetailNotaBarangMasuks\Pages;

use App\Filament\Resources\DetailNotaBarangMasuks\DetailNotaBarangMasukResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailNotaBarangMasuks extends ListRecords
{
    protected static string $resource = DetailNotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //  CreateAction::make(),
        ];
    }
}
