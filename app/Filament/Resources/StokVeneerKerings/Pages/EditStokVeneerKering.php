<?php

namespace App\Filament\Resources\StokVeneerKerings\Pages;

use App\Filament\Resources\StokVeneerKerings\StokVeneerKeringResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditStokVeneerKering extends EditRecord
{
    protected static string $resource = StokVeneerKeringResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
