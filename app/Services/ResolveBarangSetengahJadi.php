<?php

namespace App\Services;

use App\Models\BarangSetengahJadiHp;
use App\Models\Grade;
use App\Models\JenisBarang;
use App\Models\SerahTerimaHp;
use App\Models\Ukuran;
use Illuminate\Support\Facades\Log;

/**
 * Mencari BarangSetengahJadiHp untuk palet yang berasal dari gudang mentah
 * (Platform Mentah / Triplek Jadi / Triplek Mentah). Palet jenis ini hanya
 * menyimpan jenis kayu, kw_grade & ukuran di tabel mutasi keluar, sehingga
 * id_barang_setengah_jadi di modal_sandings jadi NULL dan barangnya tidak
 * muncul di dropdown Hasil Sanding.
 *
 * Service ini mencocokkan data mutasi ke master (jenis_barang, grades, ukurans)
 * lalu firstOrCreate BarangSetengahJadiHp-nya. Master TIDAK dibuat otomatis;
 * kalau tidak ketemu, return null + tulis log supaya bisa dicek manual.
 */
class ResolveBarangSetengahJadi
{
    public static function fromSerahTerima(SerahTerimaHp $st): ?BarangSetengahJadiHp
    {
        [$mutasi, $kategori] = match (true) {
            $st->id_platform_mth_mutasi_keluar !== null => [$st->platformMthMutasiKeluar, 'PLATFORM'],
            $st->id_triplek_mutasi_keluar !== null      => [$st->triplekMutasiKeluar, 'PLYWOOD'],
            $st->id_triplek_mth_mutasi_keluar !== null  => [$st->triplekMthMutasiKeluar, 'PLYWOOD'],
            default                                     => [null, null],
        };

        if (! $mutasi) {
            return null;
        }

        $namaKayu = trim((string) $mutasi->jenisKayu?->nama_kayu);
        $kwGrade  = trim((string) $mutasi->kw_grade);

        $jenis = JenisBarang::whereRaw('LOWER(nama_jenis_barang) = ?', [mb_strtolower($namaKayu)])->first();

        $grade = Grade::whereRaw('LOWER(nama_grade) = ?', [mb_strtolower($kwGrade)])
            ->whereHas('kategoriBarang', fn ($q) => $q->whereRaw('UPPER(nama_kategori) = ?', [$kategori]))
            ->first();

        $ukuran = Ukuran::where('panjang', $mutasi->panjang)
            ->where('lebar', $mutasi->lebar)
            ->where('tebal', $mutasi->tebal)
            ->first();

        if (! $jenis || ! $grade || ! $ukuran) {
            Log::warning('ResolveBarangSetengahJadi: master tidak ditemukan', [
                'id_serah_terima_hp' => $st->id,
                'kayu'   => $namaKayu . ($jenis ? '' : ' (TIDAK ADA)'),
                'grade'  => $kwGrade . ($grade ? '' : ' (TIDAK ADA)'),
                'ukuran' => "{$mutasi->panjang}x{$mutasi->lebar}x{$mutasi->tebal}" . ($ukuran ? '' : ' (TIDAK ADA)'),
            ]);

            return null;
        }

        return BarangSetengahJadiHp::firstOrCreate([
            'id_jenis_barang' => $jenis->id,
            'id_ukuran'       => $ukuran->id,
            'id_grade'        => $grade->id,
        ]);
    }
}