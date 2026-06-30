<?php

namespace App\Filament\Resources\NotaBarangMasuks\Pages;

use App\Filament\Resources\NotaBarangMasuks\NotaBarangMasukResource;
use App\Services\VeneerMutasiService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewNotaBarangMasuk extends ViewRecord
{
    protected static string $resource = NotaBarangMasukResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => $this->record->divalidasi_oleh === null),
        ];
    }
}
