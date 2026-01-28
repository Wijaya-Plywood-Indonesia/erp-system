<?php

namespace App\Filament\Resources\ProduksiPilihVeneers\Pages;

use App\Filament\Resources\ProduksiPilihVeneers\ProduksiPilihVeneerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProduksiPilihVeneer extends EditRecord
{
    protected static string $resource = ProduksiPilihVeneerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
