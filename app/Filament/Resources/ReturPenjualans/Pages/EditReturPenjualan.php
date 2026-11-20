<?php

namespace App\Filament\Resources\ReturPenjualans\Pages;

use App\Filament\Resources\ReturPenjualans\ReturPenjualanResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditReturPenjualan extends EditRecord
{
    protected static string $resource = ReturPenjualanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
