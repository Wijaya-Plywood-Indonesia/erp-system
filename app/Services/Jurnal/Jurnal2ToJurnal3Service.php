<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal2;
use App\Models\JurnalTiga;
use App\Models\AnakAkun;
use Illuminate\Support\Facades\DB;

class Jurnal2ToJurnal3Service
{
    public function sync(): int
    {
        return DB::transaction(function () {

            $rows = Jurnal2::where('status_sinkron', 'belum sinkron')->get();
            $total = 0;

            foreach ($rows as $row) {

                // 1️⃣ akun detail (7120)
                $akunDetail = AnakAkun::where(
                    'kode_anak_akun',
                    $row->no_akun
                )->first();

                if (! $akunDetail) {
                    continue;
                }

                // 2️⃣ akun seratus (parent)
                $akunSeratus = $akunDetail->parentAkun;
                if (! $akunSeratus) {
                    continue;
                }

                // 3️⃣ induk akun (1000) → dari relasi indukAkun
                $induk = $akunSeratus->indukAkun;
                if (! $induk) {
                    continue;
                }

                JurnalTiga::create([
                    'modif1000'    => $induk->kode_induk_akun,      // ✅ dari tabel induk_akuns
                    'akun_seratus' => $akunSeratus->kode_anak_akun,
                    'detail'       => $row->nama_akun,
                    'banyak'       => $row->banyak,
                    'kubikasi'     => $row->kubikasi,
                    'harga'        => $row->harga,
                    'total'        => $row->total,
                    'createdBy'    => $row->user_id,
                ]);

                $row->update([
                    'status_sinkron' => 'sudah sinkron',
                    'sinkron_at'     => now(),
                ]);

                $total++;
            }

            return $total;
        });
    }
}
