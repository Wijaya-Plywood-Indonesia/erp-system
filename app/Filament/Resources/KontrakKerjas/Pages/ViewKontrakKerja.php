<?php

namespace App\Filament\Resources\KontrakKerjas\Pages;

use App\Filament\Resources\KontrakKerjas\KontrakKerjaResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKontrakKerja extends ViewRecord
{
    protected static string $resource = KontrakKerjaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
