<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal1st;
use App\Models\Jurnal2;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Jurnal1ToJurnal2Service
{
    public function sync(): int
    {
        return DB::transaction(function () {

            /**
             * 1️⃣ Ambil jurnal 1 belum sinkron
             */
            $rows = Jurnal1st::where('status', 'Belum Sinkron')->get();

            if ($rows->isEmpty()) {
                return 0;
            }

            /**
             * 2️⃣ Mapping ke akun induk (1110 / 1200 / 1300)
             */
            $mapped = $rows->map(function ($row) {

                $akunInduk = $this->resolveAkunInduk((string) $row->no_akun);

                $master = AnakAkun::where('kode_anak_akun', $akunInduk)->first();

                if (! $master) {
                    return null;
                }

                return [
                    'jurnal1_id' => $row->id,
                    'modif100'   => $akunInduk,
                    'no_akun'    => $akunInduk,
                    'nama_akun'  => $master->nama_anak_akun,
                    'banyak'     => (int) ($row->banyak ?? 0),
                    'kubikasi'   => (float) str_replace(',', '', $row->m3 ?? 0), // ✅ FIX
                    'total'      => (int) ($row->total ?? 0),
                ];
            })->filter();


            /**
             * 3️⃣ Group by akun induk
             */
            $grouped = $mapped->groupBy('modif100');

            foreach ($grouped as $modif100 => $items) {

                Jurnal2::create([
                    'modif100'       => $modif100,
                    'no_akun'        => $modif100,
                    'nama_akun'      => $items->first()['nama_akun'],
                    'banyak'         => $items->sum('banyak'),
                    'kubikasi'       => $items->sum('kubikasi'),
                    'harga'          => 0,
                    'total'          => $items->sum('total'),
                    'user_id'        => Auth::user()->name,
                    'status_sinkron' => 'belum sinkron',
                    'synced_at'      => now(),
                    'synced_by'      => Auth::user()->name,
                ]);

                /**
                 * 4️⃣ Update jurnal 1
                 */
                Jurnal1st::whereIn('id', $items->pluck('jurnal1_id'))
                    ->update([
                        'status'    => 'Sudah Sinkron',
                        'synced_at' => now(),
                        'synced_by' => Auth::user()->name,
                    ]);
            }

            return $grouped->count();
        });
    }

    /**
     * 1111.00 → 1110
     * 1201.00 → 1200
     * 1300.10 → 1300
     */
    private function resolveAkunInduk(string $noAkun): string
    {
        $kode = substr(str_replace('.', '', $noAkun), 0, 4);
        return substr($kode, 0, 3) . '0';
    }
}
