<?php

namespace App\Filament\Resources\Mesins\Pages;

use App\Filament\Resources\Mesins\MesinResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMesin extends ViewRecord
{
    protected static string $resource = MesinResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
