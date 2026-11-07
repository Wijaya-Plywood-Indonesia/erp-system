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

            Action::make('kirim_ulang')
                ->label('Kirim ulang ke pabrik lain')
                ->icon('heroicon-o-paper-airplane')
                ->color('warning')
                ->visible(fn () => \App\Models\BkbmOutbox::where('siklus_uuid', $this->record->siklus_uuid)
                    ->where('jenis', 'buat_bm')->where('status', '!=', 'terkirim')->exists())
                ->action(function () {
                    $o = \App\Models\BkbmOutbox::where('siklus_uuid', $this->record->siklus_uuid)
                        ->where('jenis', 'buat_bm')->where('status', '!=', 'terkirim')->latest('id')->first();
                    $ok = $o && app(\App\Services\BkBm\BkBmKirimService::class)->kirim($o);
                    $n = Notification::make()->title($ok ? 'Berhasil terkirim ke pabrik lain' : 'Belum berhasil terkirim');
                    $ok ? $n->success() : $n->body($o?->fresh()?->error ?? 'Tidak ada pesan yang perlu dikirim.')->warning();
                    $n->send();
                    $this->record->refresh();
                    $this->dispatch('$refresh');
                }),

            \Filament\Actions\EditAction::make()->visible(fn () => ! $this->record->terkunci()),
        ];
    }
}
