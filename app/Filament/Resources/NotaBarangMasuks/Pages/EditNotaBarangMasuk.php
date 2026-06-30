<?php

namespace App\Filament\Resources\NotaBarangMasuks\Pages;

use App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditNotaBarangMasuk extends EditRecord
{
    protected static string $resource = NotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
