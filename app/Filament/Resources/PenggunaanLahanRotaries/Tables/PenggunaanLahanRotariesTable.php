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
                        return "**Konfirmasi Penyelesaian Lahan**\n\n" .
                            "Lahan: **{$record->lahan->kode_lahan}**\n" .
                            "Jenis Kayu: **{$namaKayu}**\n\n" .
                            "Apakah Anda yakin lahan ini sudah selesai digunakan?";
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

                            // Filter yang benar-benar punya stok > 0
                            $summariesBerstok = $summaries->where('stok_batang', '>', 0);

                            $grandTotalBatangKeluar = 0;

                            foreach ($summariesBerstok as $item) {
                                // Simpan total batang yang akan dikeluarkan
                                $batangKeluar = (int) $item->stok_batang;

                                // LANGSUNG RESET SUMMARY (TANPA LOG HPP)
                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                ]);

                                $grandTotalBatangKeluar += $batangKeluar;

                                Log::info('Stok direset (tanpa log HPP)', [
                                    'id_lahan' => $idLahan,
                                    'id_jenis_kayu' => $idJenisKayu,
                                    'panjang' => $item->panjang,
                                    'batang_direset' => $batangKeluar,
                                ]);
                            }

                            // Update record penggunaan lahan
                            $record->update([
                                'jumlah_batang' => $grandTotalBatangKeluar,
                            ]);

                            // =========================================================
                            // RESET TEMPAT KAYU (TIDAK DIHAPUS)
                            // =========================================================

                            // Update TempatKayu yang sudah ada
                            $updatedCount = DB::table('tempat_kayus')
                                ->where('id_lahan', $idLahan)
                                ->update([
                                    'jumlah_batang'   => 0,
                                    'status'          => 'belum serah',
                                    'diserahkan_oleh' => null,
                                    'diterima_oleh'   => null,
                                    'updated_at'      => now(),
                                ]);

                            // Jika belum ada TempatKayu untuk lahan ini, buat baru
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

                            // Reset pivot serah terima
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

                            Log::info('Lahan selesai direset (tanpa log HPP)', [
                                'id_lahan' => $idLahan,
                                'kode_lahan' => $record->lahan->kode_lahan,
                                'total_stok_dikeluarkan' => $grandTotalBatangKeluar,
                            ]);
                        });

                        Notification::make()
                            ->title('✅ Lahan Berhasil Diselesaikan')
                            ->body('Stok telah di-reset ke 0, Tempat Kayu tetap ada dengan status "Belum Diserahkan" dan siap digunakan kembali. (Tidak ada log HPP yang dicatat)')
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
