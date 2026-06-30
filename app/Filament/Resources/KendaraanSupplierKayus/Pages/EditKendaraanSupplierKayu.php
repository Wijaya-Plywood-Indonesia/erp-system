<?php

namespace App\Filament\Resources\KendaraanSupplierKayus\Pages;

use App\Filament\Resources\KendaraanSupplierKayus\KendaraanSupplierKayuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditKendaraanSupplierKayu extends EditRecord
{
    protected static string $resource = KendaraanSupplierKayuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
