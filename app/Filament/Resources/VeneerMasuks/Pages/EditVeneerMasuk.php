<?php

namespace App\Filament\Resources\VeneerMasuks\Pages;

use App\Filament\Resources\VeneerMasuks\VeneerMasukResource;
use App\Services\VeneerMutasiService;
use Filament\Resources\Pages\EditRecord;

class EditVeneerMasuk extends EditRecord
{
    protected static string $resource = VeneerMasukResource::class;

    /** Track whether status was 'draft' before saving */
    private string $statusBefore = 'draft';

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->statusBefore = $this->record->status;
        return $data;
    }

    protected function afterSave(): void
    {
        app(VeneerMutasiService::class)->process($this->record);
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('back')
                ->label('Kembali ke Nota BM')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => $this->record->notaBm ? route('filament.admin.resources.nota-barang-masuks.view', $this->record->notaBm->id) : $this->getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->record && $this->record->notaBm) {
            return route('filament.admin.resources.nota-barang-masuks.view', $this->record->notaBm->id);
        }
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        if ($this->record && $this->record->notaBm?->divalidasi_oleh !== null) {
            return [];
        }
        return parent::getFormActions();
    }
}
