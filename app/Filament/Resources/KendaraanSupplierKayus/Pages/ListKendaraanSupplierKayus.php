<?php

namespace App\Filament\Resources\KendaraanSupplierKayus\Pages;

use App\Filament\Resources\KendaraanSupplierKayus\KendaraanSupplierKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKendaraanSupplierKayus extends ListRecords
{
    protected static string $resource = KendaraanSupplierKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
