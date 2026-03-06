<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Str;


class NomorKontrakService
{

    protected const PREFIX = 'HRD/PKWT';

    public static function generate(?Carbon $tanggal = null): string
    {
        $tanggal ??= Carbon::now();

        $bulan = $tanggal->month;
        $tahun = $tanggal->year;
        $romawi = self::bulanRomawi($bulan);

        return DB::transaction(function () use ($romawi, $tahun) {

            // Lock baris yang sesuai agar tidak bentrok saat paralel request
            $last = DB::table('kontrak_kerja')
                ->where('no_kontrak', 'like', "%/{$romawi}/{$tahun}")
                ->lockForUpdate()
                ->orderByDesc('no_kontrak')
                ->value('no_kontrak');

            $nextNumber = 1;

            if ($last) {
                $nextNumber = self::extractUrutan($last) + 1;
            }

            $urut = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);

            return "{$urut}/" . self::PREFIX . "/{$romawi}/{$tahun}";
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
