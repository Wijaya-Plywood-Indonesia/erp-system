<?php

namespace App\Filament\Resources\NotaBarangKeluars\Pages;

use App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNotaBarangKeluar extends EditRecord
{
    protected static string $resource = NotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
