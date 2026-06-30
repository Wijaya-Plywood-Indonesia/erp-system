<?php

namespace App\Filament\Resources\DetailNotaBarangKeluars\Pages;

use App\Filament\Resources\DetailNotaBarangKeluars\DetailNotaBarangKeluarResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDetailNotaBarangKeluars extends ListRecords
{
    protected static string $resource = DetailNotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // CreateAction::make(),
        ];
    }
}
