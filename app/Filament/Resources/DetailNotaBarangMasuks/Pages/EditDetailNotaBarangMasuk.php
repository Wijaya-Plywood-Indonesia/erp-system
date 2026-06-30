<?php

namespace App\Filament\Resources\DetailNotaBarangMasuks\Pages;

use App\Filament\Resources\DetailNotaBarangMasuks\DetailNotaBarangMasukResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditDetailNotaBarangMasuk extends EditRecord
{
    protected static string $resource = DetailNotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
