<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal2;
use App\Models\JurnalTiga;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Jurnal2ToJurnal3Service
{
    public function sync(): int
    {
        return DB::transaction(function () {

            /**
             * 1️⃣ Ambil Jurnal 2 yang belum sinkron
             *    Tentukan:
             *    - akun_seratus (1100)
             *    - nama akun seratus (Kas)
             *    - modif1000 (1000)
             */
            $rows = Jurnal2::where('status_sinkron', 'belum sinkron')
                ->get()
                ->map(function ($row) {

                    // 1110 / 1120 / 1130
                    $akunDetail = AnakAkun::where('kode_anak_akun', $row->no_akun)->first();
                    if (! $akunDetail) {
                        return null;
                    }

                    // 1100 (Kas)
                    $akunSeratus = $akunDetail->parentAkun;
                    if (! $akunSeratus) {
                        return null;
                    }

                    // 1000
                    $indukAkun = $akunSeratus->indukAkun;
                    if (! $indukAkun) {
                        return null;
                    }

                    return [
                        'jurnal2_id'   => $row->id,
                        'akun_seratus' => $akunSeratus->kode_anak_akun, // 1100
                        'nama_akun'    => $akunSeratus->nama_anak_akun, // Kas
                        'modif1000'    => $indukAkun->kode_induk_akun,   // 1000
                        'banyak'       => $row->banyak ?? 0,
                        'kubikasi'     => $row->kubikasi ?? 0,
                        'harga'        => $row->harga ?? 0,
                        'total'        => $row->total ?? 0,
                    ];
                })
                ->filter();

            if ($rows->isEmpty()) {
                return 0;
            }

            /**
             * 2️⃣ GROUP BY akun_seratus (1100)
             *    Semua 1110–1190 tergabung otomatis
             */
            $grouped = $rows->groupBy('akun_seratus');

            foreach ($grouped as $akunSeratus => $items) {

                $first = $items->first();

                /**
                 * 3️⃣ INSERT KE JURNAL 3
                 */
                JurnalTiga::create([
                    'modif1000'    => $first['modif1000'],   // 1000
                    'akun_seratus' => $akunSeratus,          // 1100
                    'detail'       => $first['nama_akun'],   // Kas ✅
                    'banyak'       => $items->sum('banyak'),
                    'kubikasi'     => $items->sum('kubikasi'),
                    'harga'        => $items->sum('harga'),
                    'total'        => $items->sum('total'),
                    'createdBy'    => Auth::user()->name,
                    'status'       => 'belum sinkron',
                ]);

                /**
                 * 4️⃣ UPDATE JURNAL 2 → SUDAH SINKRON
                 */
                Jurnal2::whereIn('id', $items->pluck('jurnal2_id'))
                    ->update([
                        'status_sinkron' => 'sudah sinkron',
                        'synced_at'      => now(),
                        'synced_by'      => Auth::user()->name,
                    ]);
            }

            // jumlah akun seratus (1100, 1200, dst)
            return $grouped->count();
        });
    }
}
