<?php

namespace App\Filament\Resources\DetailHasilPaletRotaries\Pages;

use App\Filament\Resources\DetailHasilPaletRotaries\DetailHasilPaletRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailHasilPaletRotary extends EditRecord
{
    protected static string $resource = DetailHasilPaletRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
