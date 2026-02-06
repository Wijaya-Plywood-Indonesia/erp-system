<?php

namespace App\Services\Jurnal;

use App\Models\JurnalUmum;
use App\Models\Jurnal1st;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class JurnalUmumToJurnal1Service
{
    public function sync(): int
    {
        return DB::transaction(function () {

            $rows = JurnalUmum::where('status', 'Belum Sinkron')->get();
            $total = 0;

            foreach ($rows as $row) {

                // ambil akun detail
                $akunDetail = AnakAkun::where(
                    'kode_anak_akun',
                    intval($row->no_akun)
                )->first();

                if (! $akunDetail) {
                    continue;
                }

                // ambil induk akun (level 10)
                $induk = $akunDetail->indukAkun;
                if (! $induk) {
                    continue;
                }

                // hitung total
                $tot = 0;
                if ($row->hit_kbk === 'b') {
                    $tot = intval($row->banyak) * intval($row->harga);
                } else {
                    $tot = floatval($row->m3) * intval($row->harga);
                }

                Jurnal1st::create([
                    'modif10'    => $induk->kode_induk_akun,
                    'no_akun'    => intval($row->no_akun),
                    'nama_akun'  => $row->nama,
                    'bagian'     => $row->map,
                    'banyak'     => $row->banyak ?? 0,
                    'm3'         => $row->m3 ?? 0,
                    'harga'      => $row->harga ?? 0,
                    'total'     => $tot,
                    'created_by' => $row->created_by,
                    'status'=> 'belum sinkron',
                ]);

                $row->update([
                    'status'    => 'Sudah Sinkron',
                    'synced_at' => now(),
                    'synced_by' => Auth::user()->name,
                ]);

                $total++;
            }

            return $total;
        });
    }
}
