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
                /**
                 * Eager load stokMasuk supaya tidak terjadi N+1 query.
                 * N+1 artinya: kalau ada 100 baris, tanpa eager load
                 * Laravel akan query database 100 kali untuk cek stokMasuk.
                 * Dengan with(), cukup 1 query tambahan untuk semua baris.
                 */
                DetailHasil::query()->with(['stokMasuk', 'ukuran', 'jenisKayu', 'produksiDryer'])
            )
            ->columns([
                TextColumn::make('no_palet')
                    ->label('No. Palet')
                    ->searchable()
                    ->badge()
                    /**
                     * Sekarang kita pakai stokMasuk sebagai penentu warna,
                     * bukan is_diserahkan yang tidak ada di database
                     */
                    ->color(fn($record) => $record->stokMasuk ? 'success' : 'gray')
                    ->description(fn($record) => $record->stokMasuk ? 'Sudah Serah' : 'Belum Serah'),

                TextColumn::make('kw')
                    ->label('KW')
                    ->sortable(),

                TextColumn::make('isi')
                    ->label('Isi')
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
                    ->modalDescription('Setelah diserahkan, data ini akan masuk ke stok gudang dan tombol serah akan hilang.')
                    ->modalSubmitActionLabel('Ya, Serahkan Sekarang')
                    /**
                     * PERUBAHAN UTAMA:
                     * Hapus pengecekan is_diserahkan karena kolom tidak ada.
                     * Cukup cek stokMasuk saja — kalau null berarti belum diserahkan.
                     *
                     * is_null() lebih aman dari ! $record->stokMasuk
                     * karena memastikan benar-benar null, bukan object kosong
                     */
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

                                // 2. Ambil Snapshot Saldo Terakhir
                                $lastSnapshot = StokVeneerKering::snapshotTerakhir(
                                    $record->id_ukuran,
                                    $record->id_jenis_kayu,
                                    $record->kw
                                );

                                // 3. Masukkan ke Stok Gudang Kering
                                StokVeneerKering::create([
                                    'id_detail_hasil_dryer' => $record->id,
                                    'id_ukuran'             => $record->id_ukuran,
                                    'id_jenis_kayu'         => $record->id_jenis_kayu,
                                    'kw'                    => $record->kw,
                                    'jenis_transaksi'       => 'masuk',
                                    'tanggal_transaksi'     => now(),
                                    'qty'                   => $record->isi,
                                    'm3'                    => round($m3, 4),
                                    'stok_m3_sebelum'       => $lastSnapshot['stok_m3'] ?? 0,
                                    'stok_m3_sesudah'       => ($lastSnapshot['stok_m3'] ?? 0) + $m3,
                                    'keterangan'            => "MASUK DARI DRYER: No. Palet {$record->no_palet}",
                                ]);

                                /**
                                 * PERUBAHAN:
                                 * Kita tidak update is_diserahkan lagi karena kolom tidak ada.
                                 * Tombol otomatis hilang karena stokMasuk sudah terisi
                                 * setelah StokVeneerKering::create() di atas berhasil.
                                 * Relasi stokMasuk akan mengembalikan data, bukan null lagi.
                                 */
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
