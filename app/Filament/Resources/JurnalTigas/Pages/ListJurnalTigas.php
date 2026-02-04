<?php

namespace App\Filament\Resources\JurnalTigas\Pages;

use App\Filament\Resources\JurnalTigas\JurnalTigaResource;
use App\Models\IndukAkun;
use App\Models\JurnalTiga;
use App\Models\Neraca;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListJurnalTigas extends ListRecords
{
    protected static string $resource = JurnalTigaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncData')
                ->label('Sinkronisasi')
                ->icon('heroicon-o-arrow-path')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sinkronisasi Audit')
                ->modalDescription('Apakah Anda yakin ingin menyinkronkan data produksi ke Neraca? Tindakan ini akan mencatat identitas Anda sebagai validator dan mengunci status data menjadi "Sinkron".')
                ->modalSubmitActionLabel('Ya, Sinkronkan Data')
                ->action(function () {
                    // 1. Ambil data rekapitulasi berdasarkan kelompok akun (modif1000)
                    $rekapJurnal = JurnalTiga::query()
                        ->where('status', 'belum sinkron')
                        ->selectRaw('modif1000, SUM(banyak) as total_banyak, SUM(kubikasi) as total_m3, SUM(harga) as total_harga, SUM(total) as grand_total')
                        ->groupBy('modif1000')
                        ->get();

                    // Cek jika tidak ada data untuk disinkronkan
                    if ($rekapJurnal->isEmpty()) {
                        Notification::make()
                            ->title('Gagal Sinkronisasi')
                            ->body('Tidak ditemukan data baru dengan status "Belum Sinkron".')
                            ->danger()
                            ->send();
                        return;
                    }

                    // Menyiapkan rincian untuk laporan log audit
                    $rincianAudit = [];

                    foreach ($rekapJurnal as $item) {
                        // 2. Ambil keterangan nama induk akun
                        $ketSeribu = IndukAkun::where('kode_induk_akun', $item->modif1000)->value('nama_induk_akun');

                        // 3. Update atau Create data di tabel Neraca
                        Neraca::updateOrCreate(
                            ['akun_seribu' => $item->modif1000],
                            [
                                'detail'   => $ketSeribu,
                                'banyak'   => $item->total_banyak,
                                'kubikasi' => $item->total_m3,
                                'harga'    => $item->total_harga,
                                'total'    => $item->grand_total,
                            ]
                        );

                        // 4. Update status dan catat identitas validator pada tabel JurnalTiga
                        JurnalTiga::where('modif1000', $item->modif1000)
                            ->where('status', 'belum sinkron')
                            ->update([
                                'status' => 'sinkron',
                                'synchronized_by' => auth()->user()->name, // Mencatat user yang mengeksekusi
                                'synchronized_at' => now(), // Mencatat waktu presisi
                            ]);

                        // Simpan rincian per akun ke array untuk log aktivitas
                        $rincianAudit[] = [
                            'akun' => $item->modif1000,
                            'm3' => $item->total_m3,
                            'total_rp' => $item->grand_total
                        ];
                    }

                    // 5. LOG AKTIVITAS KOMPLEKS: Mencatat perpindahan ke Audit Log
                    activity()
                        ->inLog('Jurnal 3rd') // Mengisi kolom Modul
                        ->event('Sync')      // Mengisi kolom Event dengan badge
                        ->causedBy(auth()->user())
                        ->withProperties([
                            'modul_asal' => 'Jurnal 3rd',
                            'modul_tujuan' => 'Neraca',
                            'rincian_akun' => $rincianAudit, // Data detail untuk tim audit
                            'jumlah_kelompok' => count($rincianAudit)
                        ])
                        ->log('Menjalankan Sinkronisasi Massal ke Tabel Neraca');

                    Notification::make()
                        ->title('Sinkronisasi Berhasil')
                        ->body('Data produksi telah berhasil direkap ke Neraca. Jejak audit telah disimpan.')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('New Jurnal 3'),
        ];
    }
}
