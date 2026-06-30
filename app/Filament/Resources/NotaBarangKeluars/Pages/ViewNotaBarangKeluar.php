<?php

namespace App\Filament\Resources\NotaBarangKeluars\Pages;

use App\Filament\Resources\NotaBarangKeluars\NotaBarangKeluarResource;
use App\Services\VeneerMutasiService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewNotaBarangKeluar extends ViewRecord
{
    protected static string $resource = NotaBarangKeluarResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->visible(fn () => $this->record->divalidasi_oleh === null),
        ];
    }
}
