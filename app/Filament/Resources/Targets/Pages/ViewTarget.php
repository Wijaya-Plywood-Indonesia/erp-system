<?php

namespace App\Filament\Resources\Targets\Pages;

use App\Filament\Resources\Targets\TargetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTarget extends ViewRecord
{
    protected static string $resource = TargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
