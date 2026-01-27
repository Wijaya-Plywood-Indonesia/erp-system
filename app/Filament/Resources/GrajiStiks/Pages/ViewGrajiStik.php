<?php

namespace App\Filament\Resources\GrajiStiks\Pages;

use App\Filament\Resources\GrajiStiks\GrajiStikResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewGrajiStik extends ViewRecord
{
    protected static string $resource = GrajiStikResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
