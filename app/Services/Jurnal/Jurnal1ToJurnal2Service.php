<?php

namespace App\Services\Jurnal;

use App\Models\Jurnal1st;
use App\Models\Jurnal2;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class Jurnal1ToJurnal2Service
{
    public function sync(): int
    {
        return DB::transaction(function () {

            $rows = Jurnal1st::where('status', 'Belum Sinkron')
                ->selectRaw('
                    modif10,
                    no_akun,
                    nama_akun,
                    SUM(banyak) as total_banyak,
                    SUM(m3) as total_m3,
                    SUM(total) as grand_total
                ')
                ->groupBy('modif10', 'no_akun', 'nama_akun')
                ->get();

            foreach ($rows as $row) {
                Jurnal2::create([
                    'modif100'      => $row->modif10,
                    'no_akun'       => $row->no_akun,
                    'nama_akun'     => $row->nama_akun,
                    'banyak'        => $row->total_banyak,
                    'kubikasi'      => $row->total_m3,
                    'harga'         => '-',
                    'total'         => $row->grand_total,
                    'user_id'       => Auth::user()->name,
                    'status_sinkron'=> 'belum sinkron',
                    'synced_at'     => now(),
                    'synced_by'     => Auth::user()->name,
                ]);
            }

            // update jurnal 1
            Jurnal1st::where('status', 'Belum Sinkron')->update([
                'status'    => 'Sudah Sinkron',
                'synced_at' => now(),
                'synced_by' => Auth::user()->name,
            ]);

            return $rows->count();
        });
    }
}
