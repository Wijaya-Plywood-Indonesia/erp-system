<?php

namespace App\Console\Commands;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\StokTriplekJadi;
use App\Models\Ukuran;
use Illuminate\Console\Command;

class DebugBarangSetengahJadiHp extends Command
{
    /**
     * Contoh pakai:
     *   php artisan debug:stok-bshp            -> cek SEMUA stok, tampilkan mana yang gagal
     *   php artisan debug:stok-bshp 5           -> cek satu stok dengan id = 5
     */
    protected $signature = 'debug:stok-bshp {id? : ID stok_triplek_jadi yang mau dicek}';

    protected $description = 'Debug pencocokan StokTriplekJadi ke BarangSetengahJadiHp';

    public function handle(): int
    {
        $id = $this->argument('id');

        $query = StokTriplekJadi::with('jenisKayu')->where('stok_lembar', '>', 0);

        if ($id) {
            $query->where('id', $id);
        }

        $stokList = $query->get();

        if ($stokList->isEmpty()) {
            $this->error('Tidak ada data stok ditemukan.');

            return self::FAILURE;
        }

        foreach ($stokList as $stok) {
            $this->newLine();
            $this->info("=== Stok #{$stok->id} ===");

            $this->table(
                ['Field', 'Nilai', 'Tipe PHP'],
                [
                    ['panjang', $stok->panjang, gettype($stok->panjang)],
                    ['lebar', $stok->lebar, gettype($stok->lebar)],
                    ['tebal', $stok->tebal, gettype($stok->tebal)],
                    ['id_jenis_kayu', $stok->id_jenis_kayu, gettype($stok->id_jenis_kayu)],
                    ['kw_grade', $stok->kw_grade, gettype($stok->kw_grade)],
                ]
            );

            // Step 1: cari Ukuran
            $ukuran = Ukuran::where('panjang', $stok->panjang)
                ->where('lebar', $stok->lebar)
                ->where('tebal', $stok->tebal)
                ->first();

            if (! $ukuran) {
                $this->error("❌ Ukuran TIDAK ditemukan untuk panjang={$stok->panjang}, lebar={$stok->lebar}, tebal={$stok->tebal}");

                $mirip = Ukuran::all(['id', 'panjang', 'lebar', 'tebal']);
                $this->warn('Daftar semua Ukuran yang ada di DB (bandingkan manual, cek tipe/format):');
                $this->table(
                    ['id', 'panjang', 'lebar', 'tebal'],
                    $mirip->map(fn ($u) => [
                        $u->id,
                        $u->panjang.' ('.gettype($u->panjang).')',
                        $u->lebar.' ('.gettype($u->lebar).')',
                        $u->tebal.' ('.gettype($u->tebal).')',
                    ])->toArray()
                );

                continue;
            }

            $this->info("✅ Ukuran ditemukan: id={$ukuran->id} ({$ukuran->panjang} x {$ukuran->lebar} x {$ukuran->tebal})");

            // Step 2: cari Grade berdasarkan kw_grade (teks) -> nama_grade, case-insensitive
            $grade = Grade::whereRaw('LOWER(nama_grade) = ?', [strtolower(trim($stok->kw_grade))])->first();

            if (! $grade) {
                $this->error("❌ Grade TIDAK ditemukan untuk kw_grade='{$stok->kw_grade}'");

                $this->warn('Daftar semua Grade yang ada di DB (bandingkan manual):');
                $this->table(
                    ['id', 'nama_grade'],
                    Grade::all(['id', 'nama_grade'])->toArray()
                );

                continue;
            }

            $this->info("✅ Grade ditemukan: id={$grade->id} (nama_grade='{$grade->nama_grade}')");

            // Step 3: cari BarangSetengahJadiHp
            $bshp = BarangSetengahJadiHp::where('id_ukuran', $ukuran->id)
                ->where('id_jenis_barang', $stok->id_jenis_kayu)
                ->where('id_grade', $grade->id)
                ->first();

            if ($bshp) {
                $this->info("✅ BarangSetengahJadiHp ditemukan: id={$bshp->id}");

                continue;
            }

            $this->error('❌ BarangSetengahJadiHp TIDAK ditemukan dengan kombinasi:');
            $this->table(
                ['id_ukuran', 'id_jenis_barang', 'id_grade'],
                [[$ukuran->id, $stok->id_jenis_kayu, $grade->id]]
            );

            // Tampilkan kandidat dengan id_ukuran yang sama, untuk bandingkan jenis_barang/grade
            $kandidat = BarangSetengahJadiHp::where('id_ukuran', $ukuran->id)
                ->get(['id', 'id_jenis_barang', 'id_grade']);

            if ($kandidat->isEmpty()) {
                $this->warn("Tidak ada satupun baris BarangSetengahJadiHp dengan id_ukuran={$ukuran->id}. Berarti ukuran ini belum pernah didaftarkan sama sekali di sana.");
            } else {
                $this->warn('Ditemukan baris dengan id_ukuran yang sama, tapi jenis_barang/grade beda (bandingkan manual):');
                $this->table(
                    ['id', 'id_jenis_barang', 'id_grade'],
                    $kandidat->map(fn ($b) => [
                        $b->id,
                        $b->id_jenis_barang.' ('.gettype($b->id_jenis_barang).')',
                        $b->id_grade.' ('.gettype($b->id_grade).')',
                    ])->toArray()
                );
            }
        }

        return self::SUCCESS;
    }
}
