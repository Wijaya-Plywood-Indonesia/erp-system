<?php

namespace App\Filament\Resources\OngkosProduksiDryers\Pages;

use App\Filament\Resources\OngkosProduksiDryers\OngkosProduksiDryerResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewOngkosProduksiDryer extends ViewRecord
{
    protected static string $resource = OngkosProduksiDryerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()->visible(fn() => !$this->record->is_final),
        ];
    }
}