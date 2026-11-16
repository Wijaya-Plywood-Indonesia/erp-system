<?php

namespace App\Services\BkBm;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Nomor "{TIPE} {urut4}-{DD}{NN}", contoh "BKL 0001-1301":
 *   urut4 = nomor urut per jenis, mulai 0001, lanjut dari angka tertinggi di nota BK/BM baru saja (terpisah dari menu lama).
 *   DD    = tanggal pada nota (2 digit).
 *   NN    = urutan nota jenis itu pada tanggal tersebut (2 digit), mulai 01 dan kembali ke 01 tiap hari.
 * Huruf kapital.
 * Dikunci lewat tabel bkbm_counter + unique index no_nota, jadi tidak bisa kembar.
 * Panggil DI DALAM transaksi yang menyimpan nota.
 */
class NomorBkBmService
{
    public static function generate(string $tipe, Carbon $tanggal): string
    {
        $tipe = strtolower($tipe);

        return DB::transaction(function () use ($tipe, $tanggal) {
            DB::table('bkbm_counter')->insertOrIgnore(['tipe' => $tipe, 'terakhir' => 0]);
            $row = DB::table('bkbm_counter')->where('tipe', $tipe)->lockForUpdate()->first();

            $dasar = max(
                (int) $row->terakhir,
                (int) config("bkbm.nomor_awal.$tipe", 0),
                self::maksDi(\App\Models\Bk::class, $tipe),
                self::maksDi(\App\Models\Bm::class, $tipe),
            );

            $next = $dasar >= 9999 ? 1 : $dasar + 1;
            DB::table('bkbm_counter')->where('tipe', $tipe)->update(['terakhir' => $next]);

            return sprintf('%s %04d-%s%02d', strtoupper($tipe), $next, $tanggal->format('d'), self::urutHarian($tipe, $tanggal));
        });
    }

    /** Saran nomor berikutnya (tanpa mengunci/menyimpan). Pengguna boleh mengubahnya. */
    public static function saran(string $tipe, Carbon $tanggal): string
    {
        $tipe = strtolower($tipe);
        $dasar = max(
            (int) config("bkbm.nomor_awal.$tipe", 0),
            self::maksDi(\App\Models\Bk::class, $tipe),
            self::maksDi(\App\Models\Bm::class, $tipe),
        );
        $next = $dasar >= 9999 ? 1 : $dasar + 1;
        return sprintf('%s %04d-%s%02d', strtoupper($tipe), $next, $tanggal->format('d'), self::urutHarian($tipe, $tanggal));
    }

    /** Urutan ke berapa nota jenis ini pada tanggal tersebut (dihitung dari nomor yang sudah ada). */
    private static function urutHarian(string $tipe, Carbon $tanggal): int
    {
        $hari = $tanggal->format('d');
        $maks = 0;
        foreach ([\App\Models\Bk::class, \App\Models\Bm::class] as $model) {
            $daftar = $model::query()->whereDate('tanggal', $tanggal->toDateString())->where('no_nota', 'like', "{$tipe} %")->pluck('no_nota');
            foreach ($daftar as $no) {
                if (preg_match('/^' . preg_quote($tipe, '/') . ' \d+-' . $hari . '(\d{2})$/i', $no, $m)) {
                    $maks = max($maks, (int) $m[1]);
                }
            }
        }
        return min($maks + 1, 99);
    }

    private static function maksDi(string $model, string $tipe): int
    {
        $maks = 0;
        foreach ($model::query()->where('no_nota', 'like', "{$tipe} %")->pluck('no_nota') as $no) {
            if (preg_match('/^' . preg_quote($tipe, '/') . ' (\d+)-/i', $no, $m)) {
                $maks = max($maks, (int) $m[1]);
            }
        }
        return $maks;
    }
}