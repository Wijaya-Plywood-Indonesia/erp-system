<?php

namespace App\Filament\Resources\Bks\Pages;

use App\Filament\Resources\Bks\BkResource;
use App\Services\BkBm\BkBmService;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBk extends ViewRecord
{
    protected static string $resource = BkResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validasi')
                ->label('Validasi Nota')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->visible(fn () => ! $this->record->sudahDivalidasi())
                ->requiresConfirmation()
                ->modalDescription('Setelah divalidasi stok berkurang dan nota tidak bisa diubah.')
                ->action(function () {
                    try {
                        app(BkBmService::class)->validasiBk($this->record);
                        Notification::make()->title('Nota divalidasi')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Gagal validasi')->body($e->getMessage())->danger()->send();
                    }
                    $this->record->refresh();
                    $this->refreshFormData(['status', 'divalidasi_oleh']);
                }),

            \App\Filament\Resources\Bks\Actions\BarangKembaliAction::make(),

            \Filament\Actions\EditAction::make()->visible(fn () => ! $this->record->terkunci()),
        ];
    }
}
