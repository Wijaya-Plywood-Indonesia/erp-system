<?php

namespace App\Filament\Resources\HargaVeneers\Pages;

use App\Filament\Resources\HargaVeneers\HargaVeneerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewHargaVeneer extends ViewRecord
{
    protected static string $resource = HargaVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
