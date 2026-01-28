<?php

namespace App\Filament\Resources\PegawaiGrajiStiks\Pages;

use App\Filament\Resources\PegawaiGrajiStiks\PegawaiGrajiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPegawaiGrajiStik extends EditRecord
{
    protected static string $resource = PegawaiGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
