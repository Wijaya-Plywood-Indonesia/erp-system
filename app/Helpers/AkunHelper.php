<?php

namespace App\Helpers;

use App\Models\AnakAkun;

class AkunHelper
{
    public static function all(): array
{
    return self::accountsByIndukRange(1000, 6000);
}

    public static function debitAccounts(): array
    {
        return self::accountsByIndukRange(1000, 3000);
    }

    public static function kreditAccounts(): array
    {
        return self::accountsByIndukRange(4000, 6000);
    }

    protected static function accountsByIndukRange(int $from, int $to): array
    {
        $options = [];

        $anakAkuns = AnakAkun::with(['indukAkun', 'subAnakAkuns'])
            ->whereHas('indukAkun', function ($q) use ($from, $to) {
                $q->whereBetween('kode_induk_akun', [$from, $to]);
            })
            ->orderBy('kode_anak_akun')
            ->get();

        foreach ($anakAkuns as $anak) {

            if ($anak->subAnakAkuns->isEmpty()) {
                $kode = "{$anak->kode_anak_akun}.00";
                $options[$kode] = "{$kode} — {$anak->nama_anak_akun}";
            }

            foreach ($anak->subAnakAkuns as $sub) {
                $kode = "{$anak->kode_anak_akun}.{$sub->kode_sub_anak_akun}";
                $options[$kode] = "{$kode} — {$sub->nama_sub_anak_akun}";
            }
        }

        return $options;
    }
}
