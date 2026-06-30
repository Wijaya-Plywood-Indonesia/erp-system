<?php

namespace App\Filament\Resources\GantiPisauRotaries\Pages;

use App\Filament\Resources\GantiPisauRotaries\GantiPisauRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGantiPisauRotary extends EditRecord
{
    protected static string $resource = GantiPisauRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
