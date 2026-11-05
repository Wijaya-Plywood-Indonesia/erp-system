<?php

namespace App\Filament\Resources\Bms\Pages;

use App\Filament\Resources\Bms\BmResource;
use App\Services\BkBm\BkBmService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewBm extends ViewRecord
{
    protected static string $resource = BmResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('validasi')
                ->label('Validasi Nota')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->visible(fn () => ! $this->record->sudahDivalidasi())
                ->requiresConfirmation()
                ->modalDescription('Setelah divalidasi stok bertambah dan nota tidak bisa diubah.')
                ->action(function () {
                    try {
                        app(BkBmService::class)->validasiBm($this->record);
                        Notification::make()->title('Nota divalidasi')->success()->send();
                    } catch (\Throwable $e) {
                        Notification::make()->title('Gagal validasi')->body($e->getMessage())->danger()->send();
                    }
                    $this->record->refresh();
                    $this->refreshFormData(['status', 'divalidasi_oleh']);
                }),
            \Filament\Actions\EditAction::make()->visible(fn () => ! $this->record->terkunci()),
        ];
    }
}
