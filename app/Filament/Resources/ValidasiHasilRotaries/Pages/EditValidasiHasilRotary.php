<?php

namespace App\Filament\Resources\ValidasiHasilRotaries\Pages;

use App\Filament\Resources\ValidasiHasilRotaries\ValidasiHasilRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiHasilRotary extends EditRecord
{
    protected static string $resource = ValidasiHasilRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
