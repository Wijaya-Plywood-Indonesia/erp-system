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
            Action::make('kirim_ulang')
                ->label('Kirim ulang kabar ke pabrik asal')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => \App\Models\BkbmOutbox::where('siklus_uuid', $this->record->siklus_uuid)
                    ->where('jenis', 'status_update')->where('status', '!=', 'terkirim')->exists())
                ->action(function () {
                    $o = \App\Models\BkbmOutbox::where('siklus_uuid', $this->record->siklus_uuid)
                        ->where('jenis', 'status_update')->where('status', '!=', 'terkirim')->latest('id')->first();
                    $ok = $o && app(\App\Services\BkBm\BkBmKirimService::class)->kirim($o);
                    $n = Notification::make()->title($ok ? 'Kabar berhasil terkirim ke pabrik asal' : 'Belum berhasil terkirim');
                    $ok ? $n->success() : $n->body($o?->fresh()?->error ?? 'Tidak ada pesan yang perlu dikirim.')->warning();
                    $n->send();
                    $this->record->refresh();
                    $this->dispatch('$refresh');
                }),

            \App\Filament\Resources\Bms\Actions\KembalikanBmkAction::make(),
            \App\Filament\Resources\Bms\Actions\CetakBmAction::make(),
            \Filament\Actions\EditAction::make()->visible(fn () => ! $this->record->terkunci()),
        ];
    }
}
