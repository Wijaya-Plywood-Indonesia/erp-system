<?php

namespace App\Filament\Resources\SupplierKayus\Pages;

use App\Filament\Resources\SupplierKayus\SupplierKayuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupplierKayu extends ViewRecord
{
    protected static string $resource = SupplierKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
