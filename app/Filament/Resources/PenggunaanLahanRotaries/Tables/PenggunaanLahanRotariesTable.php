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
use Illuminate\Support\Facades\Log;

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
                    })
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
                CreateAction::make(),
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
                        $namaKayu = $record->jenisKayu?->nama_kayu ?? 'N/A';
                        return "Apakah Anda yakin lahan {$record->lahan->kode_lahan} sudah kosong?\n\n" .
                            "Sistem akan:\n" .
                            "1. Menghabiskan stok {$namaKayu} di lahan ini (Reset 0).\n" .
                            "2. Mencatat Log HPP Keluar sebagai 'Digunakan Produksi'.\n" .
                            "3. Meriset status Tempat Kayu menjadi 'Siap Diisi'.";
                    })
                    ->action(function (PenggunaanLahanRotary $record) {
                        $idLahan     = $record->id_lahan;
                        $idJenisKayu = $record->id_jenis_kayu;

                        // Guard: pastikan id_jenis_kayu tidak null
                        if (is_null($idJenisKayu)) {
                            Notification::make()
                                ->title('Gagal: Jenis Kayu Tidak Ditemukan')
                                ->body('Record ini tidak memiliki id_jenis_kayu. Periksa data penggunaan lahan.')
                                ->danger()
                                ->send();
                            return;
                        }

                        DB::transaction(function () use ($record, $idLahan, $idJenisKayu) {

                            // Ambil semua summary untuk kombinasi lahan + jenis kayu ini
                            $summaries = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->get();

                            Log::debug('lahan_selesai summaries', [
                                'id_lahan'      => $idLahan,
                                'id_jenis_kayu' => $idJenisKayu,
                                'total_rows'    => $summaries->count(),
                                'rows'          => $summaries->toArray(),
                            ]);

                            // Filter yang benar-benar punya stok > 0
                            $summariesBerstok = $summaries->where('stok_batang', '>', 0);

                            if ($summariesBerstok->isEmpty()) {
                                Notification::make()
                                    ->title('Gagal: Stok Sudah Kosong')
                                    ->body('Tidak ada stok aktif ditemukan untuk lahan ini di tabel HPP Summary.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $grandTotalBatangKeluar = 0;

                            foreach ($summariesBerstok as $item) {
                                // ✅ Snapshot nilai SEBELUM apapun diubah
                                $batangKeluar   = (int)   $item->stok_batang;
                                $kubikasiKeluar = (float) $item->stok_kubikasi;
                                $nilaiKeluar    = (float) $item->nilai_stok;
                                $hppSaatIni     = (float) $item->hpp_average;

                                // Safeguard double-trigger: skip jika ternyata sudah 0
                                if ($batangKeluar <= 0) {
                                    Log::warning('lahan_selesai: skip item stok sudah 0', [
                                        'summary_id'    => $item->id,
                                        'id_lahan'      => $idLahan,
                                        'id_jenis_kayu' => $idJenisKayu,
                                        'panjang'       => $item->panjang,
                                        'grade'         => $item->grade,
                                    ]);
                                    continue;
                                }

                                // A. Catat Log HPP DULU sebelum summary di-reset
                                $log = HppAverageLog::create([
                                    'id_lahan'             => $idLahan,
                                    'id_jenis_kayu'        => $idJenisKayu,
                                    'grade'                => $item->grade ?? '-',
                                    'panjang'              => $item->panjang,
                                    'tanggal'              => now(),
                                    'tipe_transaksi'       => 'keluar',
                                    'keterangan'           => "Digunakan produksi rotary (Lahan Selesai) - Seri Lahan: {$record->lahan->kode_lahan}",
                                    'referensi_type'       => PenggunaanLahanRotary::class,
                                    'referensi_id'         => $record->id,
                                    'total_batang'         => $batangKeluar,
                                    'total_kubikasi'       => round($kubikasiKeluar, 4),
                                    'harga'                => $hppSaatIni,
                                    'nilai_stok'           => $nilaiKeluar,
                                    // Before = snapshot sebelum reset
                                    'stok_batang_before'   => $batangKeluar,
                                    'stok_kubikasi_before' => round($kubikasiKeluar, 4),
                                    'nilai_stok_before'    => $nilaiKeluar,
                                    // After = 0 karena direset habis
                                    'stok_batang_after'    => 0,
                                    'stok_kubikasi_after'  => 0,
                                    'nilai_stok_after'     => 0,
                                    'hpp_average'          => 0,
                                ]);

                                // B. Reset summary SETELAH log berhasil dibuat
                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                    'id_last_log'   => $log->id,
                                ]);

                                $grandTotalBatangKeluar += $batangKeluar;
                            }

                            // Update jumlah_batang di record penggunaan lahan
                            $record->update([
                                'jumlah_batang' => $grandTotalBatangKeluar,
                            ]);

                            // Reset Tempat Kayu (status fisik)
                            DB::table('tempat_kayus')
                                ->where('id_lahan', $idLahan)
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'status'          => 'siap_diisi',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);

                            // Reset Pivot Serah Terima
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