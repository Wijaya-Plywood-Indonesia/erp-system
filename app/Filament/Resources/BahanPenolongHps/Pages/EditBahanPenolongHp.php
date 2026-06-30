<?php

namespace App\Filament\Resources\BahanPenolongHps\Pages;

use App\Filament\Resources\BahanPenolongHps\BahanPenolongHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditBahanPenolongHp extends EditRecord
{
    protected static string $resource = BahanPenolongHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
