<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NomorKontrakService
{
    public static function generate()
    {
        $now = Carbon::now();
        $bulan = $now->month;
        $tahun = $now->year;

        $romawi = [
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
            12 => "XII"
        ][$bulan];

        $last = DB::table('kontrak_kerja')
            ->whereMonth('created_at', $bulan)
            ->whereYear('created_at', $tahun)
            ->latest('id')
            ->value('no_kontrak');

        $num = 1;
        if ($last) {
            $parts = explode('/', $last);
            $num = intval($parts[0]) + 1;
        }

        $urut = str_pad($num, 3, '0', STR_PAD_LEFT);
        return "{$urut}/HRD/PKWT/{$romawi}/{$tahun}";
    }
}
