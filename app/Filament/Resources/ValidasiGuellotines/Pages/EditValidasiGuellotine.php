<?php

namespace App\Filament\Resources\ValidasiGuellotines\Pages;

use App\Filament\Resources\ValidasiGuellotines\ValidasiGuellotineResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiGuellotine extends EditRecord
{
    protected static string $resource = ValidasiGuellotineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
