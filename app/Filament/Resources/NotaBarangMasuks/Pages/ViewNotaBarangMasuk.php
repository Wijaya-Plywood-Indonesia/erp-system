<?php

namespace App\Filament\Resources\NotaBarangMasuks\Pages;

use App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewNotaBarangMasuk extends ViewRecord
{
    protected static string $resource = NotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn($record) => $record->divalidasi_oleh === null),

        ];
    }
}
