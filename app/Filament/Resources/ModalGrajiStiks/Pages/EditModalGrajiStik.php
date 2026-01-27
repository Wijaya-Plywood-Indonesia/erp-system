<?php

namespace App\Filament\Resources\ModalGrajiStiks\Pages;

use App\Filament\Resources\ModalGrajiStiks\ModalGrajiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditModalGrajiStik extends EditRecord
{
    protected static string $resource = ModalGrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
