<?php

namespace App\Filament\Resources\HargaVeneers\Pages;

use App\Filament\Resources\HargaVeneers\HargaVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHargaVeneer extends EditRecord
{
    protected static string $resource = HargaVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
