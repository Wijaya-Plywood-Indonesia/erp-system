<?php

namespace App\Filament\Resources\Perusahaans\Pages;

use App\Filament\Resources\Perusahaans\PerusahaanResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerusahaan extends ViewRecord
{
    protected static string $resource = PerusahaanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
