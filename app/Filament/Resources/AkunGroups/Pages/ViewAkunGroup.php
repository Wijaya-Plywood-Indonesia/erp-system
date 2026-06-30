<?php

namespace App\Filament\Resources\AkunGroups\Pages;

use App\Filament\Resources\AkunGroups\AkunGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAkunGroup extends ViewRecord
{
    protected static string $resource = AkunGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
