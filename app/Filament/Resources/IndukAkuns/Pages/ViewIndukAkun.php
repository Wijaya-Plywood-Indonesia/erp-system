<?php

namespace App\Filament\Resources\IndukAkuns\Pages;

use App\Filament\Resources\IndukAkuns\IndukAkunResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewIndukAkun extends ViewRecord
{
    protected static string $resource = IndukAkunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
