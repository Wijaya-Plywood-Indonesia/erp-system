<?php

namespace App\Filament\Resources\UkuranBarangSetengahJadis\Pages;

use App\Filament\Resources\UkuranBarangSetengahJadis\UkuranBarangSetengahJadiResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUkuranBarangSetengahJadi extends EditRecord
{
    protected static string $resource = UkuranBarangSetengahJadiResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
