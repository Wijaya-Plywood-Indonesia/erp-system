<?php

namespace App\Console\Commands;

use App\Services\HppVeneerJadiService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Mengisi HPP riwayat veneer jadi yang lama (semua masih 0).
 *
 *   php artisan hpp:hitung-ulang-veneer-jadi            # SIMULASI: hitung lalu dibatalkan (tidak menyimpan)
 *   php artisan hpp:hitung-ulang-veneer-jadi --commit   # simpan ke database (BACKUP DULU)
 *   php artisan hpp:hitung-ulang-veneer-jadi --kayu=9   # satu jenis kayu saja
 */
class HitungUlangHppVeneerJadi extends Command
{
    protected $signature = 'hpp:hitung-ulang-veneer-jadi {--commit : Simpan hasil (tanpa ini hanya simulasi)} {--kayu= : id jenis kayu}';

    protected $description = 'Hitung ulang HPP seluruh riwayat veneer jadi (kayu, pekerja, mesin, bahan, lahan) dan nilai stoknya';

    public function handle(HppVeneerJadiService $service): int
    {
        $commit = (bool) $this->option('commit');

        $this->info(($commit ? 'MENYIMPAN' : 'SIMULASI (tidak disimpan)').': memutar ulang seluruh log veneer jadi berurutan...');

        DB::beginTransaction();
        try {
            $hasil = $service->hitungUlangSemua($this->option('kayu') ? (int) $this->option('kayu') : null);
            $commit ? DB::commit() : DB::rollBack();
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Dibatalkan: '.$e->getMessage());

            return self::FAILURE;
        }

        $ringkas = array_map(fn ($p) => [$p['kayu'], $p['ukuran'], $p['kw'], $p['log'], $p['masuk'], number_format($p['hpp'], 0, ',', '.')], $hasil['produk']);
        $totalLog = $hasil['log'];
        $totalHpp = $hasil['masuk_berhpp'];
        $nilai    = $hasil['nilai_akhir'];

        usort($ringkas, fn ($a, $b) => $b[3] <=> $a[3]);
        $this->table(['Kayu', 'Ukuran', 'KW', 'Log', 'Masuk berHPP', 'HPP stok akhir'], array_slice($ringkas, 0, 15));
        $this->info("Log diproses: {$totalLog} · pemasukan yang berhasil berHPP: {$totalHpp} · nilai stok akhir total: Rp ".number_format($nilai, 0, ',', '.'));

        foreach (array_slice($service->ambilCatatan(), 0, 25) as $c) {
            $this->warn($c);
        }

        $this->line($commit ? 'Tersimpan.' : 'Simulasi selesai. Jalankan lagi dengan --commit untuk menyimpan.');

        return self::SUCCESS;
    }
}