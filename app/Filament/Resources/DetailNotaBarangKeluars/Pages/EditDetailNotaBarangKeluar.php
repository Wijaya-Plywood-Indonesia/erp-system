<?php

namespace App\Filament\Resources\DetailNotaBarangKeluars\Pages;

use App\Filament\Resources\DetailNotaBarangKeluars\DetailNotaBarangKeluarResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailNotaBarangKeluar extends EditRecord
{
    protected static string $resource = DetailNotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
