<?php

namespace App\Filament\Resources\Ukurans\Pages;

use App\Filament\Resources\Ukurans\UkuranResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewUkuran extends ViewRecord
{
    protected static string $resource = UkuranResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
