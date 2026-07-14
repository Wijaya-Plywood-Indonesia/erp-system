<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;

class NomorKontrakService
{
    protected const PREFIX = 'HRD/PKWT';

    /**
     * Generate nomor kontrak berikutnya.
     *
     * PENTING: parameter $tanggal HARUS diisi dengan tanggal KONTRAK MULAI
     * (bukan tanggal/waktu saat tombol digenerate), supaya bulan romawi &
     * tahun pada nomor kontrak mengikuti tanggal kontrak, bukan tanggal
     * form diisi. Kalau tidak dikirim, fallback ke waktu sekarang.
     */
    public static function generate(?Carbon $tanggal = null): string
    {
        $tanggal ??= Carbon::now();

        $bulan = $tanggal->month;
        $tahun = $tanggal->year;
        $romawi = self::bulanRomawi($bulan);

        return DB::transaction(function () use ($romawi, $tahun) {

            // Advisory lock (khusus MySQL) berdasarkan bulan+tahun romawi.
            // Ini penting karena tiap interaksi Livewire (afterStateUpdated)
            // adalah request terpisah -> lockForUpdate() di bawah saja tidak
            // cukup, sebab baris yang mau dikunci belum tentu ada di DB saat
            // dua user sama-sama masih mengisi form Create secara bersamaan.
            // Dengan GET_LOCK, request kedua akan menunggu (maks 10 detik)
            // sampai request pertama selesai menghitung nomornya.
            $lockName = "no_kontrak_{$romawi}_{$tahun}";
            DB::statement('SELECT GET_LOCK(?, 10)', [$lockName]);

            try {
                $last = DB::table('kontrak_kerja')
                    ->where('no_kontrak', 'like', "%/{$romawi}/{$tahun}")
                    ->lockForUpdate()
                    ->orderByDesc('no_kontrak')
                    ->value('no_kontrak');

                $nextNumber = $last ? self::extractUrutan($last) + 1 : 1;

                $urut = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

                return "{$urut}/" . self::PREFIX . "/{$romawi}/{$tahun}";
            } finally {
                DB::statement('SELECT RELEASE_LOCK(?)', [$lockName]);
            }
        });
    }

    protected static function extractUrutan(string $noKontrak): int
    {
        return (int) Str::before($noKontrak, '/');
    }

    protected static function bulanRomawi(int $bulan): string
    {
        return [
            1 => "I",
            2 => "II",
            3 => "III",
            4 => "IV",
            5 => "V",
            6 => "VI",
            7 => "VII",
            8 => "VIII",
            9 => "IX",
            10 => "X",
            11 => "XI",
            12 => "XII",
        ][$bulan];
    }
}