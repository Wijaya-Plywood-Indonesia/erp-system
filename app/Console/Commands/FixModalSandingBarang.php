<?php

namespace App\Console\Commands;

use App\Models\ModalSanding;
use App\Services\ResolveBarangSetengahJadi;
use Illuminate\Console\Command;

class FixModalSandingBarang extends Command
{
    protected $signature = 'sanding:fix-modal-barang {--dry-run : Hanya tampilkan, jangan simpan}';

    protected $description = 'Isi id_barang_setengah_jadi pada modal_sandings yang NULL (palet dari gudang mentah)';

    public function handle(): int
    {
        $dry = $this->option('dry-run');
        $ok = $gagal = 0;

        ModalSanding::whereNull('id_barang_setengah_jadi')
            ->whereNotNull('id_serah_terima_hp')
            ->with([
                'serahTerimaHp.platformMthMutasiKeluar.jenisKayu',
                'serahTerimaHp.triplekMutasiKeluar.jenisKayu',
                'serahTerimaHp.triplekMthMutasiKeluar.jenisKayu',
            ])
            ->each(function (ModalSanding $m) use ($dry, &$ok, &$gagal) {
                $barang = $m->serahTerimaHp
                    ? ResolveBarangSetengahJadi::fromSerahTerima($m->serahTerimaHp)
                    : null;

                if (! $barang) {
                    $gagal++;
                    $this->warn("Modal #{$m->id}: tidak bisa dicocokkan (lihat storage/logs/laravel.log)");

                    return;
                }

                $ok++;
                $this->line("Modal #{$m->id} -> barang_setengah_jadi #{$barang->id}");

                if (! $dry) {
                    // Pakai query builder agar observer tidak ikut jalan lagi
                    ModalSanding::whereKey($m->id)->update(['id_barang_setengah_jadi' => $barang->id]);
                }
            });

        $this->info(($dry ? '[DRY RUN] ' : '') . "Berhasil: {$ok}, gagal: {$gagal}");

        return self::SUCCESS;
    }
}