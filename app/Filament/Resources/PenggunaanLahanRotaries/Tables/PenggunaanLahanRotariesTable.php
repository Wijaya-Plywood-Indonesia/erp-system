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
                    ->modalHeading('Konfirmasi Pengosongan Lahan')
                    ->modalDescription(function ($record) {
                        return "Apakah lahan {$record->lahan->kode_lahan} sudah benar-benar kosong? \n\n" .
                            "Sistem akan menghabiskan SELURUH stok yang tersisa di lahan ini dan mencatatnya ke LOG HPP.";
                    })
                    ->action(function (PenggunaanLahanRotary $record) {
                        DB::transaction(function () use ($record) {
                            $idLahan = $record->id_lahan;

                            /**
                             * PERBAIKAN UTAMA:
                             * Ambil SEMUA stok yang tersisa di lahan ini (id_lahan).
                             * Kita tidak memfilter id_jenis_kayu agar jika ada sisa kayu jenis lain 
                             * di lahan yang sama, stoknya juga ikut dibersihkan (nertibkan data).
                             */
                            $summaries = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('stok_batang', '>', 0)
                                ->get();

                            if ($summaries->isEmpty()) {
                                Notification::make()
                                    ->title('Informasi: Stok Sudah Nol')
                                    ->body('Lahan ini sudah tidak memiliki stok aktif di sistem.')
                                    ->info()
                                    ->send();

                                // Tetap jalankan reset fisik untuk memastikan status 'siap_diisi'
                                self::resetFisikLahan($idLahan);
                                return;
                            }

                            $grandTotalBatangKeluar = 0;

                            foreach ($summaries as $item) {
                                $batangKeluar   = $item->stok_batang;
                                $kubikasiKeluar = $item->stok_kubikasi;
                                $nilaiKeluar    = $item->nilai_stok;
                                $hppSaatIni     = $item->hpp_average;

                                // 1. BUAT LOG HPP (Pencatatan Sejarah)
                                $log = HppAverageLog::create([
                                    'id_lahan'              => $idLahan,
                                    'id_jenis_kayu'         => $item->id_jenis_kayu,
                                    'grade'                 => $item->grade ?? '-',
                                    'panjang'               => $item->panjang,
                                    'tanggal'               => now(),
                                    'tipe_transaksi'        => 'keluar',
                                    'keterangan'            => "Lahan Selesai: Digunakan Produksi (Ref: {$record->lahan->kode_lahan})",
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

                                // 2. RESET SUMMARY (Menghapus Saldo)
                                $item->update([
                                    'stok_batang'   => 0,
                                    'stok_kubikasi' => 0,
                                    'nilai_stok'    => 0,
                                    'hpp_average'   => 0,
                                    'id_last_log'   => $log->id, // Hubungkan ke log terakhir
                                ]);

                                $grandTotalBatangKeluar += $batangKeluar;
                            }

                            // 3. Update angka pada record penggunaan (Snapshot terakhir)
                            $record->update([
                                'jumlah_batang' => $grandTotalBatangKeluar,
                            ]);

                            // 4. Jalankan Reset Fisik
                            self::resetFisikLahan($idLahan);
                        });

                        Notification::make()
                            ->title('Lahan Berhasil Diselesaikan')
                            ->body('Stok telah dipindahkan ke Log HPP dan status fisik direset.')
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
