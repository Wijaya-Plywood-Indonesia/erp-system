<?php

namespace App\Services\Jurnal;

use App\Models\IndukAkun;
use App\Models\JurnalTiga;
use App\Models\Neraca;
use Illuminate\Support\Facades\Auth;

class Jurnal3ToNeracaService
{
    public function sync(): int
    {
        $rekapJurnal = JurnalTiga::query()
            ->where('status', 'belum sinkron')
            ->selectRaw('modif1000, SUM(banyak) as total_banyak, SUM(kubikasi) as total_m3, SUM(harga) as total_harga, SUM(total) as grand_total')
            ->groupBy('modif1000')
            ->get();

        if ($rekapJurnal->isEmpty()) {
            return 0;
        }

        foreach ($rekapJurnal as $item) {

            $ketSeribu = IndukAkun::where(
                'kode_induk_akun',
                $item->modif1000
            )->value('nama_induk_akun');

            Neraca::updateOrCreate(
                ['akun_seribu' => $item->modif1000],
                [
                    'detail'   => $ketSeribu,
                    'banyak'   => $item->total_banyak,
                    'kubikasi' => $item->total_m3,
                    'harga'    => $item->total_harga,
                    'total'    => $item->grand_total,
                ]
            );

            JurnalTiga::where('modif1000', $item->modif1000)
                ->where('status', 'belum sinkron')
                ->update([
                    'status' => 'sinkron',
                    'synchronized_by' => Auth::user()->name,
                    'synchronized_at' => now(),
                ]);
        }

        return $rekapJurnal->count();
    }
}
