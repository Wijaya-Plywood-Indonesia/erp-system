<?php

namespace App\Filament\Resources\VeneerKeluars\Pages;

use App\Filament\Resources\VeneerKeluars\VeneerKeluarResource;
use App\Services\VeneerMutasiService;
use Filament\Resources\Pages\EditRecord;

class EditVeneerKeluar extends EditRecord
{
    protected static string $resource = VeneerKeluarResource::class;

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
                ->label('Kembali ke Nota BK')
                ->color('gray')
                ->icon('heroicon-o-arrow-left')
                ->url(fn () => $this->record->notaBk ? route('filament.admin.resources.nota-barang-keluars.view', $this->record->notaBk->id) : $this->getResource()::getUrl('index')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        if ($this->record && $this->record->notaBk) {
            return route('filament.admin.resources.nota-barang-keluars.view', $this->record->notaBk->id);
        }
        return $this->getResource()::getUrl('index');
    }

    protected function getFormActions(): array
    {
        if ($this->record && $this->record->notaBk?->divalidasi_oleh !== null) {
            return [];
        }
        return parent::getFormActions();
    }
}
