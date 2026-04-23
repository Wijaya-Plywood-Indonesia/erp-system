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

                        $totalStok = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('id_jenis_kayu', $record->id_jenis_kayu)
                            ->sum('stok_batang');

                        return "⚠️ **Konfirmasi Penyelesaian Lahan**\n\n" .
                            "📌 **Informasi Lahan:**\n" .
                            "• Lahan: **{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}**\n" .
                            "• Jenis Kayu: **{$namaKayu}**\n" .
                            "• Total Stok yang akan direset: **{$totalStok} batang**\n\n" .
                            "Sistem akan:\n" .
                            "1. ✅ Mereset stok ke 0\n" .
                            "2. 📝 Mencatat Log HPP Keluar\n" .
                            "3. 🔄 Mereset Tempat Kayu\n\n" .
                            "Apakah Anda yakin lahan ini sudah selesai digunakan?";
                    })
                    ->action(function (PenggunaanLahanRotary $record) {
                        $idLahan     = $record->id_lahan;
                        $idJenisKayu = $record->id_jenis_kayu;

                        if (is_null($idJenisKayu)) {
                            Notification::make()
                                ->title('Gagal: Jenis Kayu Tidak Ditemukan')
                                ->body('Record ini tidak memiliki id_jenis_kayu. Periksa data penggunaan lahan.')
                                ->danger()
                                ->send();
                            return;
                        }

                        DB::transaction(function () use ($record, $idLahan, $idJenisKayu) {

                            // Ambil informasi tambahan untuk keterangan
                            $kodeLahan = $record->lahan->kode_lahan ?? 'N/A';
                            $namaLahan = $record->lahan->nama_lahan ?? 'N/A';
                            $namaKayu = $record->jenisKayu->nama_kayu ?? 'N/A';

                            // ✅ PERBAIKAN: Query langsung ke database (tanpa relasi)
                            $mesinInfo = 'N/A';
                            $tanggalProduksi = '-';

                            // Coba ambil dari detail hasil palet rotary
                            $detailPalet = DB::table('detail_hasil_palet_rotaries')
                                ->where('id_penggunaan_lahan', $record->id)
                                ->first();

                            if ($detailPalet) {
                                // Ambil dari produksi rotary berdasarkan id_produksi di detail palet
                                if ($detailPalet->id_produksi) {
                                    $produksi = DB::table('produksi_rotaries')
                                        ->leftJoin('mesins', 'produksi_rotaries.id_mesin', '=', 'mesins.id')
                                        ->where('produksi_rotaries.id', $detailPalet->id_produksi)
                                        ->select('produksi_rotaries.tgl_produksi', 'mesins.nama_mesin')
                                        ->first();

                                    if ($produksi) {
                                        $mesinInfo = $produksi->nama_mesin ?? 'N/A';
                                        $tanggalProduksi = $produksi->tgl_produksi ?? '-';
                                    }
                                }
                            }

                            // Jika masih belum ada, coba ambil dari penggunaan lahan langsung
                            if ($mesinInfo === 'N/A' && $record->id_produksi) {
                                $produksi = DB::table('produksi_rotaries')
                                    ->leftJoin('mesins', 'produksi_rotaries.id_mesin', '=', 'mesins.id')
                                    ->where('produksi_rotaries.id', $record->id_produksi)
                                    ->select('produksi_rotaries.tgl_produksi', 'mesins.nama_mesin')
                                    ->first();

                                if ($produksi) {
                                    $mesinInfo = $produksi->nama_mesin ?? 'N/A';
                                    $tanggalProduksi = $produksi->tgl_produksi ?? '-';
                                }
                            }

                            // ✅ Fallback: tebak dari panjang kayu jika masih N/A
                            if ($mesinInfo === 'N/A') {
                                $firstSummary = HppAverageSummarie::where('id_lahan', $idLahan)->first();
                                if ($firstSummary) {
                                    $panjang = $firstSummary->panjang;
                                    if ($panjang == 130) {
                                        $mesinInfo = 'SANJI / YUEQUN';
                                    } elseif ($panjang == 260) {
                                        $mesinInfo = 'SPINDLESS / MERANTI';
                                    } else {
                                        $mesinInfo = 'Mesin Unknown';
                                    }
                                }
                            }

                            // Format tanggal
                            if ($tanggalProduksi && $tanggalProduksi !== '-') {
                                try {
                                    $tanggalProduksi = \Carbon\Carbon::parse($tanggalProduksi)->format('d/m/Y');
                                } catch (\Exception $e) {
                                    $tanggalProduksi = '-';
                                }
                            }

                            // Informasi lengkap untuk keterangan
                            $keteranganLengkap = sprintf(
                                "SELESAI LAHAN | Lahan: %s - %s | Jenis Kayu: %s | Mesin: %s | Tgl Produksi: %s",
                                $kodeLahan,
                                $namaLahan,
                                $namaKayu,
                                $mesinInfo,
                                $tanggalProduksi
                            );

                            $summaries = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->get();

                            $summariesBerstok = $summaries->where('stok_batang', '>', 0);

                            $grandTotalBatangKeluar = 0;
                            $grandTotalKubikasiKeluar = 0;
                            $grandTotalNilaiKeluar = 0;

                            foreach ($summariesBerstok as $item) {
                                $batangKeluar   = (int)   $item->stok_batang;
                                $kubikasiKeluar = (float) $item->stok_kubikasi;
                                $nilaiKeluar    = (float) $item->nilai_stok;
                                $hppSaatIni     = (float) $item->hpp_average;

                                $log = HppAverageLog::create([
                                    'id_lahan'             => $idLahan,
                                    'id_jenis_kayu'        => $idJenisKayu,
                                    'grade'                => null,
                                    'panjang'              => $item->panjang,
                                    'tanggal'              => now(),
                                    'tipe_transaksi'       => 'keluar',
                                    'keterangan'           => $keteranganLengkap,
                                    'referensi_type'       => PenggunaanLahanRotary::class,
                                    'referensi_id'         => $record->id,
                                    'total_batang'         => $batangKeluar,
                                    'total_kubikasi'       => round($kubikasiKeluar, 4),
                                    'harga'                => $hppSaatIni,
                                    'nilai_stok'           => $nilaiKeluar,
                                    'stok_batang_before'   => $batangKeluar,
                                    'stok_kubikasi_before' => round($kubikasiKeluar, 4),
                                    'nilai_stok_before'    => $nilaiKeluar,
                                    'stok_batang_after'    => 0,
                                    'stok_kubikasi_after'  => 0,
                                    'nilai_stok_after'     => 0,
                                    'hpp_average'          => 0,
                                ]);

                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                    'id_last_log'   => $log->id,
                                ]);

                                $grandTotalBatangKeluar   += $batangKeluar;
                                $grandTotalKubikasiKeluar += $kubikasiKeluar;
                                $grandTotalNilaiKeluar    += $nilaiKeluar;
                            }

                            $record->update([
                                'jumlah_batang' => $grandTotalBatangKeluar,
                            ]);

                            $updatedCount = DB::table('tempat_kayus')
                                ->where('id_lahan', $idLahan)
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'status'          => 'belum serah',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);

                            if ($updatedCount === 0) {
                                $kayuMasuk = \App\Models\KayuMasuk::whereHas('detailTurusanKayus', function ($q) use ($idLahan) {
                                    $q->where('lahan_id', $idLahan);
                                })->first();

                                if ($kayuMasuk) {
                                    DB::table('tempat_kayus')->insert([
                                        'id_lahan'      => $idLahan,
                                        'id_kayu_masuk' => $kayuMasuk->id,
                                        'jumlah_batang' => 0,
                                        'status'        => 'belum serah',
                                        'created_at'    => now(),
                                        'updated_at'    => now(),
                                    ]);
                                }
                            }

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
                            ->title('✅ Lahan Berhasil Diselesaikan')
                            ->body('Stok telah di-reset ke 0, Log HPP tercatat dengan informasi lengkap, dan Tempat Kayu siap digunakan kembali.')
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
