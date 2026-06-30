<?php

namespace App\Filament\Resources\HasilGrajiStiks\Pages;

use App\Filament\Resources\HasilGrajiStiks\HasilGrajiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHasilGrajiStik extends EditRecord
{
    protected static string $resource = HasilGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
