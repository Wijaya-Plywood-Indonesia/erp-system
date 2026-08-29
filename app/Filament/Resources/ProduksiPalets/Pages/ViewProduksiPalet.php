<?php

namespace App\Filament\Resources\ProduksiPalets\Pages;

use App\Filament\Resources\ProduksiPalets\ProduksiPaletResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProduksiPalet extends ViewRecord
{
    protected static string $resource = ProduksiPaletResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
