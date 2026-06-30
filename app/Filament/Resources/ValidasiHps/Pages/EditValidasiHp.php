<?php

namespace App\Filament\Resources\ValidasiHps\Pages;

use App\Filament\Resources\ValidasiHps\ValidasiHpResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiHp extends EditRecord
{
    protected static string $resource = ValidasiHpResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
