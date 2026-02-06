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

            $rows = Jurnal2::where('status_sinkron', 'belum sinkron')
                ->get()
                ->map(function ($row) {

                    /**
                     * 1️⃣ CARI AKUN BERDASARKAN no_akun
                     * Bisa akun detail (1310) atau akun seratus (1300)
                     */
                    $akun = AnakAkun::where('kode_anak_akun', $row->no_akun)->first();

                    if (! $akun) {
                        return null; // akun tidak terdaftar
                    }

                    /**
                     * 2️⃣ TENTUKAN AKUN SERATUS
                     * - Jika punya parent → dia akun detail
                     * - Jika tidak → dia akun seratus
                     */
                    $akunSeratus = $akun->parentAkun ?? $akun;

                    /**
                     * 3️⃣ INDUK AKUN (1000)
                     */
                    $indukAkun = $akunSeratus->indukAkun;

                    if (! $indukAkun) {
                        return null;
                    }

                    return [
                        'jurnal2_id'   => $row->id,
                        'akun_seratus' => $akunSeratus->kode_anak_akun, // 1100 / 1300
                        'nama_akun'    => $akunSeratus->nama_anak_akun,
                        'modif1000'    => $indukAkun->kode_induk_akun,  // 1000
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
             * 4️⃣ GROUP BY AKUN SERATUS
             * 1110 + 1120 → 1100
             * 1310 + 1320 → 1300
             * 1300 langsung → 1300
             */
            $grouped = $rows->groupBy('akun_seratus');

            foreach ($grouped as $akunSeratus => $items) {

                $first = $items->first();

                /**
                 * 5️⃣ INSERT KE JURNAL 3
                 */
                JurnalTiga::create([
                    'modif1000'    => $first['modif1000'],
                    'akun_seratus' => $akunSeratus,
                    'detail'       => $first['nama_akun'],
                    'banyak'       => $items->sum('banyak'),
                    'kubikasi'     => $items->sum('kubikasi'),
                    'harga'        => $items->sum('harga'),
                    'total'        => $items->sum('total'),
                    'createdBy'    => Auth::user()->name,
                    'status'       => 'belum sinkron',
                ]);

                /**
                 * 6️⃣ UPDATE JURNAL 2
                 */
                Jurnal2::whereIn('id', $items->pluck('jurnal2_id'))
                    ->update([
                        'status_sinkron' => 'sudah sinkron',
                        'synced_at'      => now(),
                        'synced_by'      => Auth::user()->name,
                    ]);
            }

            return $grouped->count();
        });
    }
}
