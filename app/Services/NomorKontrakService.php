<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class NomorKontrakService
{
    public static function generate()
    {
        $now = Carbon::now();

        $bulan = $now->format('n');
        $tahun = $now->format('Y');

        // ROMAWI
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
            12 => "XII",
        ][$bulan];

        // Ambil nomor urut terakhir bulan ini
        $last = DB::table('kontrak_kerja')
            ->whereMonth('tanggal_kontrak', $bulan)
            ->whereYear('tanggal_kontrak', $tahun)
            ->orderBy('no_kontrak', 'desc')
            ->value('no_kontrak');

        // Extract angka depannya (NNN)
        if ($last) {
            $num = intval(substr($last, 0, 3)) + 1;
        } else {
            $num = 1;
        }

        $urut = str_pad($num, 3, '0', STR_PAD_LEFT);

        // Format final
        $final = "{$urut}/HRD/PKWT/{$romawi}/{$tahun}";

        return $final;
    }
}
