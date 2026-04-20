<?php

namespace App\Filament\Resources\DetailHasils\Tables;

use App\Models\DetailHasil;
use App\Models\StokVeneerKering;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

class DetailHasilsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(
                // Eager load semua relasi termasuk stokMasuk untuk identifikasi status
                DetailHasil::query()->with(['stokMasuk', 'ukuran', 'jenisKayu', 'produksiDryer'])
            )
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    ->color(fn($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn($record) => $record->stokMasuk 
                        ? 'Sudah Serah (' . $record->stokMasuk->tanggal_transaksi->format('d/m/Y') . ')' 
                        : 'Belum Serah'),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Isi (Lbr)')
                    ->sortable(),

                TextColumn::make('jenisKayu.nama_kayu')
                    ->label('Jenis Kayu'),

                TextColumn::make('produksiDryer.tanggal_produksi')
                    ->label('Tgl Produksi')
                    ->date('d/m/Y'),
            ])
            ->recordActions([
                Action::make('serahKeGudang')
                    ->label('Serahkan Hasil')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Serahkan Palet ke Gudang Kering?')
                    ->modalDescription('Setelah diserahkan, data ini akan masuk ke stok gudang dan saldo lembar/m3 akan bertambah.')
                    ->modalSubmitActionLabel('Ya, Serahkan Sekarang')
                    ->visible(fn($record) => is_null($record->stokMasuk))
                    ->action(function (DetailHasil $record) {
                        try {
                            DB::transaction(function () use ($record) {
                                $ukuran = $record->ukuran;

                                if (!$ukuran) {
                                    throw new \Exception("Gagal: Dimensi ukuran palet tidak ditemukan.");
                                }

                                // 1. Hitung Kubikasi (m3)
                                $m3 = ($ukuran->panjang * $ukuran->lebar * $ukuran->tebal * $record->isi) / 1000000;

                                // 2. Ambil Snapshot Saldo Terakhir (m3 & hpp)
                                $lastSnapshot = StokVeneerKering::snapshotTerakhir(
                                    $record->id_ukuran,
                                    $record->id_jenis_kayu,
                                    $record->kw
                                );

                                /**
                                 * 3. Ambil Saldo Lembar Terakhir secara manual 
                                 * (Pastikan kolom stok_lembar_sesudah ada di table stok_veneer_kerings)
                                 */
                                $lastRecord = StokVeneerKering::forProduk(
                                    $record->id_ukuran,
                                    $record->id_jenis_kayu,
                                    $record->kw
                                )->orderByDesc('tanggal_transaksi')->orderByDesc('id')->first();

                                $qtySebelum = $lastRecord ? (float) $lastRecord->stok_lembar_sesudah : 0;

                                // 4. Simpan ke Log Stok Gudang Kering
                                StokVeneerKering::create([
                                    'id_detail_hasil_dryer' => $record->id,
                                    'id_ukuran'             => $record->id_ukuran,
                                    'id_jenis_kayu'         => $record->id_jenis_kayu,
                                    'kw'                    => $record->kw,
                                    'jenis_transaksi'       => 'masuk',
                                    'tanggal_transaksi'     => now(),
                                    'qty'                   => $record->isi,
                                    'm3'                    => round($m3, 4),
                                    
                                    // Pencatatan saldo Lembar (Agar muncul di log Sebelum -> Sesudah)
                                    'stok_lembar_sebelum'   => $qtySebelum,
                                    'stok_lembar_sesudah'   => $qtySebelum + $record->isi,
                                    
                                    // Pencatatan saldo m3
                                    'stok_m3_sebelum'       => $lastSnapshot['stok_m3'] ?? 0,
                                    'stok_m3_sesudah'       => ($lastSnapshot['stok_m3'] ?? 0) + $m3,
                                    
                                    // Keterangan lebih spesifik (Menampilkan Shift dan No Palet)
                                    'keterangan'            => "MASUK DARI DRYER: No. Palet {$record->no_palet} | Shift: " . ($record->produksiDryer->shift ?? '-'),
                                ]);
                            });

                            Notification::make()
                                ->title('Penyerahan Berhasil')
                                ->body("Palet {$record->no_palet} telah dipindahkan ke stok gudang.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Terjadi Kesalahan Sistem')
                                ->body($e->getMessage())
                                ->danger()
                                ->persistent()
                                ->send();
                        }
                    }),

                EditAction::make()
                    ->hidden(fn($record) => ! is_null($record->stokMasuk)),

                DeleteAction::make()
                    ->hidden(fn($record) => ! is_null($record->stokMasuk)),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}