<?php

namespace App\Filament\Resources\ValidasiGrajiStiks\Pages;

use App\Filament\Resources\ValidasiGrajiStiks\ValidasiGrajiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditValidasiGrajiStik extends EditRecord
{
    protected static string $resource = ValidasiGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
