<?php

namespace App\Filament\Resources\SupplierKayus\Pages;

use App\Filament\Resources\SupplierKayus\SupplierKayuResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSupplierKayus extends ListRecords
{
    protected static string $resource = SupplierKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
