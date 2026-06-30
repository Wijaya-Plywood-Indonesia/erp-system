<?php

namespace App\Filament\Resources\SupplierKayus\Pages;

use App\Filament\Resources\SupplierKayus\SupplierKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupplierKayu extends EditRecord
{
    protected static string $resource = SupplierKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
