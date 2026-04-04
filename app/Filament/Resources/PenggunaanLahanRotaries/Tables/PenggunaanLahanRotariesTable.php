<?php

namespace App\Filament\Resources\PenggunaanLahanRotaries\Tables;

use App\Models\HppAverageLog;
use App\Models\HppAverageSummarie;
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
                Action::make('lahan_selesai')
                    ->label('Lahan Selesai')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Tandai Lahan Selesai?')
                    ->modalDescription(
                        fn($record) =>
                        "Stok kayu dari lahan {$record->lahan->kode_lahan} - {$record->lahan->nama_lahan} " .
                            "({$record->jenisKayu?->nama_kayu}) akan dicatat keluar dan jumlah batang " .
                            "di penggunaan lahan ini akan diupdate sesuai stok."
                    )
                    ->modalSubmitActionLabel('Ya, Selesaikan')
                    ->action(function ($record) {
                        DB::transaction(function () use ($record) {
                            $idLahan     = $record->id_lahan;
                            $idJenisKayu = $record->id_jenis_kayu;

                            $stoks = HppAverageSummarie::where('id_lahan', $idLahan)
                                ->where('id_jenis_kayu', $idJenisKayu)
                                ->where('stok_batang', '>', 0)
                                ->get();

                            \Illuminate\Support\Facades\Log::channel('single')->info('Lahan Selesai - Stok Ditemukan', [
                                'count'  => $stoks->count(),
                                'stoks'  => $stoks->toArray(),
                            ]);

                            if ($stoks->isEmpty()) {
                                Notification::make()
                                    ->title('Tidak ada stok yang perlu diselesaikan')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            $totalBatang   = $stoks->sum('stok_batang');
                            $totalKubikasi = $stoks->sum('stok_kubikasi');
                            $totalNilai    = $stoks->sum('nilai_stok');

                            foreach ($stoks as $stok) {
                                $log = HppAverageLog::create([
                                    'id_lahan'             => $idLahan,
                                    'id_jenis_kayu'        => $idJenisKayu,
                                    'grade'                => $stok->grade,
                                    'panjang'              => $stok->panjang,
                                    'tanggal'              => now()->toDateString(),
                                    'tipe_transaksi'       => 'keluar',
                                    'keterangan'           => "Lahan selesai digunakan - {$record->lahan->kode_lahan} ({$record->jenisKayu?->nama_kayu})",
                                    'referensi_id'         => $record->id,
                                    'referensi_type'       => \App\Models\PenggunaanLahanRotary::class,
                                    'total_batang'         => $stok->stok_batang,
                                    'total_kubikasi'       => $stok->stok_kubikasi,
                                    'harga'                => $stok->hpp_average,
                                    'nilai_stok'           => $stok->nilai_stok,
                                    'stok_batang_before'   => $stok->stok_batang,
                                    'stok_kubikasi_before' => $stok->stok_kubikasi,
                                    'nilai_stok_before'    => $stok->nilai_stok,
                                    'stok_batang_after'    => 0,
                                    'stok_kubikasi_after'  => 0,
                                    'nilai_stok_after'     => 0,
                                    'hpp_average'          => $stok->hpp_average,
                                ]);

                                // Coba DB::table langsung sebagai alternatif update model
                                $affected = DB::table('hpp_average_summaries')
                                    ->where('id', $stok->id)
                                    ->update([
                                        'stok_batang'   => 0,
                                        'stok_kubikasi' => 0,
                                        'nilai_stok'    => 0,
                                        'id_last_log'   => $log->id,
                                        'updated_at'    => now(),
                                    ]);

                                \Illuminate\Support\Facades\Log::channel('single')->info('Stok Update Result', [
                                    'stok_id'      => $stok->id,
                                    'affected_rows' => $affected,
                                    'log_id'       => $log->id,
                                ]);
                            }

                            $record->update([
                                'jumlah_batang' => $totalBatang,
                            ]);

                            \Illuminate\Support\Facades\Log::channel('single')->info('Lahan Selesai - Selesai', [
                                'id_penggunaan_lahan' => $record->id,
                                'total_batang'        => $totalBatang,
                            ]);
                        });

                        Notification::make()
                            ->title('Lahan berhasil diselesaikan')
                            ->body('Stok kayu telah dicatat keluar.')
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
