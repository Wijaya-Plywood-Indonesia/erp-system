<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Models\Bk;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

/**
 * Tombol "Tandai Selesai" manual: untuk BK yang barangnya sudah kembali tetapi BM-nya dibuat sendiri
 * (tidak lewat tombol Barang Kembali), sehingga BK tidak pernah menjadi hijau.
 *
 * PENGATURAN:
 *  - Matikan/nyalakan: ubah 'tandai_selesai_aktif' (true/false) di config/bkbm.php, lalu deploy.
 *  - Siapa yang boleh: daftar $peran di bawah ini.
 * Stok tidak berubah (stok sudah diurus BM yang dibuat sendiri).
 */
class TandaiSelesaiAction
{
    /** Peran yang boleh memakai tombol ini. */
    public const PERAN = ['super_admin', 'Super Admin'];

    public static function make(): Action
    {
        return Action::make('tandai_selesai')
            ->label('Tandai Selesai')
            ->color('gray')
            ->icon('heroicon-o-check-circle')
            ->visible(fn (Bk $record) => (bool) config('bkbm.tandai_selesai_aktif', true)
                && (bool) auth()->user()?->hasAnyRole(self::PERAN)
                && in_array($record->status, ['divalidasi', 'dipinjam'], true))
            ->modalHeading('Tandai Selesai (manual)')
            ->modalDescription('Pakai hanya jika barang sudah kembali tetapi BM-nya dibuat sendiri. Stok tidak berubah. Alasan dicatat di Riwayat.')
            ->schema([
                Textarea::make('alasan')->label('Alasan')->rows(3)->required()->maxLength(500)
                    ->placeholder('Contoh: barang sudah kembali, BM 0007-1111'),
            ])
            ->action(function (array $data, Bk $record) {
                if (! in_array($record->status, ['divalidasi', 'dipinjam'], true)) {
                    Notification::make()->title('Status nota sudah berubah')->warning()->send();
                    return;
                }
                $record->forceFill([
                    'status' => 'selesai',
                    'selesai_manual_oleh' => auth()->id(),
                    'selesai_manual_at' => now(),
                    'selesai_manual_alasan' => trim($data['alasan']),
                ])->save();
                Notification::make()->title('Nota ditandai selesai')->success()->send();
            });
    }
}