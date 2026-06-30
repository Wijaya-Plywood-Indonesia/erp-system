<?php

namespace App\Filament\Resources\BahanPenolongRotaries\Pages;

use App\Filament\Resources\BahanPenolongRotaries\BahanPenolongRotaryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanPenolongRotary extends EditRecord
{
    protected static string $resource = BahanPenolongRotaryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
