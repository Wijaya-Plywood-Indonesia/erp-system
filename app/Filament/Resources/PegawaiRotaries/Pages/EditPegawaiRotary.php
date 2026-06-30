<?php

namespace App\Filament\Resources\PegawaiRotaries\Pages;

use App\Filament\Resources\PegawaiRotaries\PegawaiRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiRotary extends EditRecord
{
    protected static string $resource = PegawaiRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
