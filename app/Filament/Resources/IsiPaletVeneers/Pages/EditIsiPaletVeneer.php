<?php

namespace App\Filament\Resources\IsiPaletVeneers\Pages;

use App\Filament\Resources\IsiPaletVeneers\IsiPaletVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditIsiPaletVeneer extends EditRecord
{
    protected static string $resource = IsiPaletVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
