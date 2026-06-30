<?php

namespace App\Filament\Resources\Lahans\Pages;

use App\Filament\Resources\Lahans\LahanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLahan extends ViewRecord
{
    protected static string $resource = LahanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
