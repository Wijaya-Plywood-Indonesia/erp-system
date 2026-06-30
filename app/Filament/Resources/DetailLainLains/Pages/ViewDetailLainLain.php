<?php

namespace App\Filament\Resources\DetailLainLains\Pages;

use App\Filament\Resources\DetailLainLains\DetailLainLainResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewDetailLainLain extends ViewRecord
{
    protected static string $resource = DetailLainLainResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
