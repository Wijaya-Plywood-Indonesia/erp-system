<?php

namespace App\Filament\Resources\Bks\Actions;

use App\Models\BarangUmum;
use App\Models\Bk;
use App\Models\JenisKayu;
use App\Services\BkBm\BkBmPlywoodPlatformService;
use App\Services\BkBm\BkBmTriplekService;
use App\Services\BkBm\BkBmVeneerService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;

/**
 * Tombol "Tambah Item Barang" (dropdown) untuk tabel barang BK dan BM,
 * sama seperti menu lama: Plywood, Veneer, Barang Umum, Log Core, Platform, Lainnya.
 * Stok TIDAK berubah di sini, baru saat Validasi Nota.
 */
class TambahItemGroup
{
    public const PREFIX_LOG_CORE = 'Log Core - ';

    private static function jalankan(callable $fn): void
    {
        try {
            $fn();
            Notification::make()->title('Barang ditambahkan')->success()->send();
        } catch (\Throwable $e) {
            Notification::make()->title('Gagal')->body($e->getMessage())->danger()->send();
        }
    }

    public static function make(bool $keluar): ActionGroup
    {
        $F = ItemBarangForms::class;

        return ActionGroup::make([
            Action::make('tambah_plywood')->label('Plywood')->icon('heroicon-o-squares-2x2')
                ->schema($F::plywoodTipe($keluar))
                ->action(fn (array $data, $livewire) => self::jalankan(function () use ($data, $livewire, $keluar) {
                    $tipe = $data['tipe_plywood'] ?? 'siap_jual';
                    if ($tipe !== 'siap_jual') {
                        app(BkBmTriplekService::class)->tambahItem($livewire->getOwnerRecord(), $tipe, [
                            'id_ukuran' => $data['tp_id_ukuran'],
                            'id_jenis' => $data['tp_id_jenis_kayu'],
                            'kw_grade' => $data['tp_kw_grade'],
                            'qty' => $data['tp_jumlah'],
                            'keterangan' => $data['tp_keterangan'] ?? null,
                        ]);
                        return;
                    }
                    $idUkuran = $keluar ? ItemBarangForms::resolveUkuran($data['ukuran_key'])->id : $data['id_ukuran'];
                    app(BkBmPlywoodPlatformService::class)->tambahItem($livewire->getOwnerRecord(), 'plywood', [
                        'id_ukuran' => $idUkuran,
                        'id_jenis' => $data['id_jenis_kayu'],
                        'kw_grade' => $data['kw_grade'],
                        'qty' => $data['jumlah'],
                        'harga' => $data['harga'] ?? null,
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);
                })),

            Action::make('tambah_veneer')->label('Veneer')->icon('heroicon-o-beaker')
                ->schema($F::veneer($keluar))
                ->action(fn (array $data, $livewire) => self::jalankan(function () use ($data, $livewire) {
                    app(BkBmVeneerService::class)->tambahItem($livewire->getOwnerRecord(), [
                        'tipe_veneer' => $data['tipe_veneer'],
                        'id_ukuran' => $data['id_ukuran'],
                        'id_jenis_kayu' => $data['id_jenis_kayu'],
                        'kw' => $data['kw'],
                        'qty' => $data['jumlah'],
                        'harga' => $data['harga'] ?? null,
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);
                })),

            Action::make('tambah_barang_umum')->label('Barang Umum')->icon('heroicon-o-archive-box')
                ->schema($F::barangUmum($keluar))
                ->action(fn (array $data, $livewire) => self::jalankan(function () use ($data, $livewire) {
                    $barang = BarangUmum::findOrFail($data['id_barang_umum']);
                    $livewire->getOwnerRecord()->detail()->create([
                        'nama_barang' => config('bkbm.prefix_barang_umum') . $barang->nama_barang,
                        'jumlah' => $data['jumlah'],
                        'satuan' => $barang->satuan,
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);
                })),

            Action::make('tambah_log_core')->label('Log Core')->icon('heroicon-o-cube')
                ->schema($F::logCore($keluar))
                ->action(fn (array $data, $livewire) => self::jalankan(function () use ($data, $livewire) {
                    $kayu = JenisKayu::findOrFail($data['id_jenis_kayu']);
                    $livewire->getOwnerRecord()->detail()->create([
                        'nama_barang' => self::PREFIX_LOG_CORE . $kayu->nama_kayu . ' - ' . ItemBarangForms::fmt((float) $data['panjang']) . ' cm',
                        'jumlah' => $data['jumlah'],
                        'satuan' => 'Batang',
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);
                })),

            Action::make('tambah_platform')->label('Platform')->icon('heroicon-o-squares-2x2')
                ->schema($F::platform($keluar))
                ->action(fn (array $data, $livewire) => self::jalankan(function () use ($data, $livewire) {
                    $jadi = $data['tipe_platform'] === 'jadi';
                    app(BkBmPlywoodPlatformService::class)->tambahItem(
                        $livewire->getOwnerRecord(),
                        $jadi ? 'platform_jadi' : 'platform_mth',
                        [
                            'id_ukuran' => $data['id_ukuran'],
                            'id_jenis' => $jadi ? $data['id_jenis_barang'] : $data['id_jenis_kayu'],
                            'kw_grade' => $data['kw_grade'],
                            'qty' => $data['jumlah'],
                            'keterangan' => $data['keterangan'] ?? null,
                        ]
                    );
                })),

            Action::make('tambah_lainnya')->label('Tambah Barang (Lainnya)')->icon('heroicon-o-plus-circle')
                ->schema($F::lainnya())
                ->action(fn (array $data, $livewire) => self::jalankan(function () use ($data, $livewire) {
                    $livewire->getOwnerRecord()->detail()->create([
                        'nama_barang' => $data['nama_barang'],
                        'jumlah' => $data['jumlah'],
                        'satuan' => $data['satuan'] ?? null,
                        'keterangan' => $data['keterangan'] ?? null,
                    ]);
                })),
        ])
            ->label('Tambah Item Barang')
            ->icon('heroicon-m-plus')
            ->color('warning')
            ->button()
            ->visible(fn ($livewire) => ! $livewire->getOwnerRecord()->terkunci());
    }
}
