<?php

namespace App\Filament\Resources\KendaraanSupplierKayus\Pages;

use App\Filament\Resources\KendaraanSupplierKayus\KendaraanSupplierKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewKendaraanSupplierKayu extends ViewRecord
{
    protected static string $resource = KendaraanSupplierKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
