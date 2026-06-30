<?php

namespace App\Filament\Resources\GrajiStiks\Pages;

use App\Filament\Resources\GrajiStiks\GrajiStikResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGrajiStik extends EditRecord
{
    protected static string $resource = GrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
