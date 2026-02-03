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
                // Menambahkan pengamanan konfirmasi sebelum eksekusi
                ->requiresConfirmation()
                ->modalHeading('Konfirmasi Sinkronisasi')
                ->modalDescription('Apakah Anda yakin ingin menyinkronkan data produksi ke Neraca? Data yang sudah disinkronkan akan berubah statusnya menjadi "Sinkron".')
                ->modalSubmitActionLabel('Ya, Sinkronkan')
                ->action(function () {
                    // 1. Ambil data rekapitulasi termasuk total_harga
                    $rekapJurnal = JurnalTiga::query()
                        ->selectRaw('modif1000, SUM(banyak) as total_banyak, SUM(kubikasi) as total_m3, SUM(harga) as total_harga, SUM(total) as grand_total')
                        ->groupBy('modif1000')
                        ->get();

                    foreach ($rekapJurnal as $item) {
                        // 2. Cari nama induk (Aset/Hutang/dll)
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

                        // 4. Update status transaksi menjadi sinkron untuk audit trail
                        JurnalTiga::where('modif1000', $item->modif1000)
                            ->where('status', 'belum sinkron')
                            ->update(['status' => 'sinkron']);
                    }

                    Notification::make()
                        ->title('Sinkronisasi Berhasil')
                        ->body('Data telah direkap ke Neraca dan status telah diperbarui.')
                        ->success()
                        ->send();
                }),

            CreateAction::make()
                ->label('New Produksi3th'),
        ];
    }
}
