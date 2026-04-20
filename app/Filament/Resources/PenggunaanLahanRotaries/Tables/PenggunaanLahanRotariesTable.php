<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Tables;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
use App\Models\PenggunaanLahanRotary;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class PenggunaanLahanRotariesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lahan_display')
                    ->label('Lahan')
                    ->getStateUsing(
                        fn($record) =>
                        "{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}"
                    )
                    ->sortable(query: function ($query, string $direction) {
                        $query->join('lahans', 'penggunaan_lahan_rotaries.id_lahan', '=', 'lahans.id')
                            ->orderBy('lahans.kode_lahan', $direction)
                            ->select('penggunaan_lahan_rotaries.*');
                    }) // optional
                    ->searchable(query: function ($query, string $search) {
                        $query->whereHas('lahan', function ($q) use ($search) {
                            $q->where('kode_lahan', 'like', "%{$search}%")
                                ->orWhere('nama_lahan', 'like', "%{$search}%");
                        });
                    }),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu')
                    ->searchable()
                    ->placeholder('Belum Daftar Jenis Kayu'),

                TextColumn::make('jumlah_batang')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(), // 👈 ini yang munculkan tombol "Tambah"
            ])
            ->filters([
                //
            ])
            ->recordActions([
                /**
                 * AKSI LAHAN SELESAI (RESET FISIK & STOK)
                 */
                Action::make('lahan_selesai')
                    ->label('Selesaikan Lahan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Konfirmasi Pengosongan Lahan & Stok')
                    ->modalDescription(function ($record) {
                        return "Apakah Anda yakin lahan {$record->lahan->kode_lahan} sudah kosong? \n\n" .
                            "Sistem akan: \n" .
                            "1. Menghabiskan stok {$record->jenisKayu->nama_kayu} di lahan ini (Reset 0). \n" .
                            "2. Mencatat Log HPP Keluar sebagai 'Digunakan Produksi'. \n" .
                            "3. Meriset status Tempat Kayu menjadi 'Siap Diisi'.";
                    })
                    ->action(function (PenggunaanLahanRotary $record) {
                        DB::transaction(function () use ($record) {
                            $idLahan = $record->id_lahan;
                            $idJenisKayu = $record->id_jenis_kayu;

                            // 1. Ambil data stok di Summary yang masih > 0 untuk Lahan & Jenis Kayu ini
                            $summaries = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->where('stok_batang', '>', 0)
                                ->get();

                            if ($summaries->isEmpty()) {
                                Notification::make()
                                    ->title('Gagal: Stok Sudah Kosong')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $grandTotalBatangKeluar = 0;

                            // 2. Loop setiap kombinasi (panjang/grade) untuk dikurangi
                            foreach ($summaries as $item) {
                                $batangKeluar = $item->stok_batang;
                                $kubikasiKeluar = $item->stok_kubikasi;
                                $nilaiKeluar = $item->nilai_stok;
                                $hppSaatIni = $item->hpp_average;

                                // A. Catat Riwayat di Log HPP (Tipe: Keluar)
                                // Pastikan grade tidak null untuk menghindari error integrity constraint
                                $log = HppAverageLog::create([
                                    'id_lahan'              => $idLahan,
                                    'id_jenis_kayu'         => $idJenisKayu,
                                    'grade'                 => $item->grade ?? '-',
                                    'panjang'               => $item->panjang,
                                    'tanggal'               => now(),
                                    'tipe_transaksi'        => 'keluar',
                                    'keterangan'            => "Digunakan produksi rotary (Lahan Selesai) - Seri Lahan: {$record->lahan->kode_lahan}",
                                    'referensi_type'        => PenggunaanLahanRotary::class,
                                    'referensi_id'          => $record->id,
                                    'total_batang'          => $batangKeluar,
                                    'total_kubikasi'        => round($kubikasiKeluar, 4),
                                    'harga'                 => $hppSaatIni,
                                    'nilai_stok'            => $nilaiKeluar,
                                    'stok_batang_before'    => $batangKeluar,
                                    'stok_kubikasi_before'  => round($kubikasiKeluar, 4),
                                    'nilai_stok_before'     => $nilaiKeluar,
                                    'stok_batang_after'     => 0,
                                    'stok_kubikasi_after'   => 0,
                                    'nilai_stok_after'      => 0,
                                    'hpp_average'           => 0,
                                ]);

                                // B. RESET SUMMARY MENJADI NOL (100%)
                                // Menghubungkan ID Log terakhir ke summary agar audit trail sinkron
                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                    'id_last_log'   => $log->id,
                                ]);

                                $grandTotalBatangKeluar += $batangKeluar;
                            }

                            // 3. Update record penggunaan lahan dengan total batang yang benar-benar keluar
                            $record->update([
                                'jumlah_batang' => $grandTotalBatangKeluar,
                            ]);

                            // 4. RESET TEMPAT KAYU (Status Fisik)
                            DB::table('tempat_kayus')
                                ->where('id_lahan', $idLahan)
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'status'          => 'siap_diisi',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);

                            // 5. RESET PIVOT SERAH TERIMA
                            DB::table('detail_hasil_palet_rotary_serah_terima_pivot')
                                ->where('id_lahan', $idLahan)
                                ->where('tipe', 'lahan_rotary')
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'kubikasi'        => 0,
                                    'status'          => 'Lahan Siap',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);
                        });

                        Notification::make()
                            ->title('Lahan Berhasil Diselesaikan')
                            ->body('Stok telah di-reset ke 0, Log HPP dicatat, dan Tempat Kayu siap diisi kembali.')
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
