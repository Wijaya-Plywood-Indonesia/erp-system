<?php

namespace App\Filament\Resources\HasilGuellotines\Pages;

use App\Filament\Resources\HasilGuellotines\HasilGuellotineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilGuellotine extends EditRecord
{
    protected static string $resource = HasilGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
