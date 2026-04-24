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
use Illuminate\Support\Facades\Auth;
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

                TextColumn::make('hpp_average')
                    ->label('HPP Terakhir')
                    ->numeric()
                    ->sortable()
                    ->formatStateUsing(
                        fn($state) => $state > 0
                            ? 'Rp ' . number_format($state, 0, ',', '.')
                            : '-' // Tampilkan '-' jika belum ada data HPP
                    )
                    ->toggleable(isToggledHiddenByDefault: true),

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

                    // ── Modal Description ─────────────────────────────────────
                    // Menampilkan ringkasan informasi sebelum user konfirmasi
                    ->modalDescription(function ($record) {
                        $namaKayu = $record->jenisKayu?->nama_kayu ?? 'N/A';

                        // Hitung total stok aktif yang akan di-reset
                        $totalStok = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('id_jenis_kayu', $record->id_jenis_kayu)
                            ->sum('stok_batang');

                        // Ambil HPP aktif saat ini dari summary (bukan dari log)
                        // Ini yang akan menjadi hpp_average (hpp terakhir) setelah selesai
                        $hppAktif = HppAverageSummarie::where('id_lahan', $record->id_lahan)
                            ->where('id_jenis_kayu', $record->id_jenis_kayu)
                            ->where('stok_batang', '>', 0)
                            ->orderByDesc('id')
                            ->value('hpp_average') ?? 0;

                        $hppAktifFormatted = $hppAktif > 0
                            ? 'Rp ' . number_format($hppAktif, 0, ',', '.')
                            : 'Belum ada data';

                        return "⚠️ **Konfirmasi Penyelesaian Lahan**\n\n" .
                            "📌 **Informasi Lahan:**\n" .
                            "• Lahan: **{$record->lahan->kode_lahan} - {$record->lahan->nama_lahan}**\n" .
                            "• Jenis Kayu: **{$namaKayu}**\n" .
                            "• Total Stok yang akan direset: **{$totalStok} batang**\n" .
                            "• HPP yang akan dicatat: **{$hppAktifFormatted}**\n\n" .
                            "Sistem akan:\n" .
                            "1. ✅ Mereset stok ke 0\n" .
                            "2. 📝 Mencatat Log HPP Keluar\n" .
                            "3. 💰 Menyimpan HPP terakhir ke record lahan\n" .
                            "4. 🔄 Mereset Tempat Kayu\n\n" .
                            "Apakah Anda yakin lahan ini sudah selesai digunakan?";
                    })

                    ->action(function (PenggunaanLahanRotary $record) {
                        $idLahan     = $record->id_lahan;
                        $idJenisKayu = $record->id_jenis_kayu;

                        // ── Validasi: jenis kayu harus ada ───────────────────
                        if (is_null($idJenisKayu)) {
                            Notification::make()
                                ->title('Gagal: Jenis Kayu Tidak Ditemukan')
                                ->body('Record ini tidak memiliki id_jenis_kayu. Periksa data penggunaan lahan.')
                                ->danger()
                                ->send();
                            return;
                        }

                        DB::transaction(function () use ($record, $idLahan, $idJenisKayu) {

                            // ══════════════════════════════════════════════════
                            // LANGKAH 1: Ambil HPP terakhir dari summary AKTIF
                            // ══════════════════════════════════════════════════
                            // PENTING: Ambil SEBELUM loop reset dimulai
                            // Karena setelah reset, hpp_average di summary = 0
                            //
                            // Mengambil dari summary yang masih berstok,
                            // diurutkan dari id terbesar agar mendapat
                            // hpp_average yang paling terkini
                            $hppTerakhir = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->where('stok_batang', '>', 0)
                                ->orderByDesc('id')
                                ->value('hpp_average') ?? 0;

                            Log::info('[Selesai Lahan] HPP terakhir diambil dari summary aktif', [
                                'id_lahan'      => $idLahan,
                                'id_jenis_kayu' => $idJenisKayu,
                                'hpp_terakhir'  => $hppTerakhir,
                            ]);

                            // ══════════════════════════════════════════════════
                            // LANGKAH 2: Siapkan informasi untuk keterangan log
                            // ══════════════════════════════════════════════════
                            $kodeLahan  = $record->lahan->kode_lahan ?? 'N/A';
                            $namaLahan  = $record->lahan->nama_lahan ?? 'N/A';
                            $namaKayu   = $record->jenisKayu->nama_kayu ?? 'N/A';

                            // ══════════════════════════════════════════════════
                            // LANGKAH 3: Loop semua summary yang masih berstok
                            // Buat log keluar & reset stok ke 0
                            // ══════════════════════════════════════════════════
                            $summaries = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->get();

                            // Hanya summary yang berstok yang diproses
                            $summariesBerstok = $summaries->where('stok_batang', '>', 0);

                            $grandTotalBatangKeluar   = 0;
                            $grandTotalKubikasiKeluar = 0;
                            $grandTotalNilaiKeluar    = 0;

                            foreach ($summariesBerstok as $item) {
                                $batangKeluar   = (int)   $item->stok_batang;
                                $kubikasiKeluar = (float) $item->stok_kubikasi;
                                $nilaiKeluar    = (float) $item->nilai_stok;
                                $hppSaatIni     = (float) $item->hpp_average;

                                // Keterangan log mencatat semua info penting
                                // termasuk HPP terakhir agar mudah dilacak
                                $keteranganLog = sprintf(
                                    'SELESAI LAHAN | Lahan: %s - %s | Jenis Kayu: %s  | HPP Terakhir: Rp %s',
                                    $kodeLahan,
                                    $namaLahan,
                                    $namaKayu,
                                    number_format($hppTerakhir, 0, ',', '.')
                                );

                                // Buat log HPP keluar
                                // hpp_average di log = HPP saat stok di-reset
                                // (bukan 0), agar ada rekam jejak HPP terakhir
                                $log = HppAverageLog::create([
                                    'id_lahan'             => $idLahan,
                                    'id_jenis_kayu'        => $idJenisKayu,
                                    'grade'                => null,
                                    'panjang'              => $item->panjang,
                                    'tanggal'              => now(),
                                    'tipe_transaksi'       => 'keluar',
                                    'keterangan'           => $keteranganLog,
                                    'referensi_type'       => PenggunaanLahanRotary::class,
                                    'referensi_id'         => $record->id,
                                    'total_batang'         => $batangKeluar,
                                    'total_kubikasi'       => round($kubikasiKeluar, 4),
                                    'harga'                => $hppSaatIni,
                                    'nilai_stok'           => $nilaiKeluar,
                                    // Kondisi stok sebelum di-reset
                                    'stok_batang_before'   => $batangKeluar,
                                    'stok_kubikasi_before' => round($kubikasiKeluar, 4),
                                    'nilai_stok_before'    => $nilaiKeluar,
                                    // Kondisi stok setelah di-reset (semua 0)
                                    'stok_batang_after'    => 0,
                                    'stok_kubikasi_after'  => 0,
                                    'nilai_stok_after'     => 0,
                                    // HPP dicatat sebagai 0 karena stok sudah habis
                                    // HPP terakhir yang valid sudah disimpan
                                    // di $hppTerakhir dan akan masuk ke record
                                    'hpp_average'          => 0,
                                ]);

                                // Reset summary ke 0 setelah log dibuat
                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                    'id_last_log'   => $log->id,
                                ]);

                                // Akumulasi grand total untuk disimpan ke record
                                $grandTotalBatangKeluar   += $batangKeluar;
                                $grandTotalKubikasiKeluar += $kubikasiKeluar;
                                $grandTotalNilaiKeluar    += $nilaiKeluar;
                            }

                            // ══════════════════════════════════════════════════
                            // LANGKAH 4: Update record PenggunaanLahanRotary
                            // ══════════════════════════════════════════════════
                            // hpp_average di sini berfungsi sebagai hpp_terakhir
                            // yaitu HPP moving average saat stok terakhir habis
                            // Diambil dari LANGKAH 1 (sebelum summary di-reset)
                            $record->update([
                                'jumlah_batang' => $grandTotalBatangKeluar,
                                'hpp_average'   => $hppTerakhir, // ✅ HPP saat stok habis
                            ]);

                            Log::info('[Selesai Lahan] Record diperbarui', [
                                'id_record'      => $record->id,
                                'jumlah_batang'  => $grandTotalBatangKeluar,
                                'hpp_average'    => $hppTerakhir,
                            ]);

                            // ══════════════════════════════════════════════════
                            // LANGKAH 5: Reset Tempat Kayu
                            // ══════════════════════════════════════════════════
                            // Update jumlah_batang ke 0 dan kembalikan status
                            // ke 'belum serah' agar lahan bisa digunakan lagi
                            $updatedCount = DB::table('tempat_kayus')
                                ->where('id_lahan', $idLahan)
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'status'          => 'belum serah',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);

                            // Jika tempat kayu belum ada, buat baru
                            // (fallback untuk data lama yang belum punya tempat kayu)
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

                            // ══════════════════════════════════════════════════
                            // LANGKAH 6: Reset pivot serah terima
                            // ══════════════════════════════════════════════════
                            // Reset data pivot agar lahan siap untuk siklus baru
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
                            ->body('Stok direset ke 0. HPP terakhir telah dicatat. Lahan siap digunakan kembali.')
                            ->success()
                            ->send();
                    }),
                // =========================================================
                // PERUBAHAN 6: EditAction - hanya untuk admin dan belum selesai
                // =========================================================
                EditAction::make()
                    ->visible(
                        fn($record) =>
                        Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false
                            && !$record->isSelesai()
                    ),

                // =========================================================
                // PERUBAHAN 7: DeleteAction - hanya untuk admin dan belum selesai
                // =========================================================
                DeleteAction::make()
                    ->visible(
                        fn($record) =>
                        Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false
                            && !$record->isSelesai()
                    ),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn() => Auth::user()?->hasAnyRole(['super_admin', 'admin']) ?? false),
                ]),
            ]);
    }
}
