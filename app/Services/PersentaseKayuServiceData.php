<?php
namespace App\Services;

use Illuminate\Support\Facades\DB;

class PersentaseKayuServiceData
{
    public function getLaporanSiklus()
    {
        $history = DB::table('log_masuks')
            ->select('tgl_masuk as tgl', 'qty as masuk', DB::raw('0 as keluar'), DB::raw("'MASUK' as tipe"))
            ->unionAll(
                DB::table('hasil_produksis')
                    ->select('tgl_produksi as tgl', DB::raw('0 as masuk'), 'qty_keluar as keluar', DB::raw("'KELUAR' as tipe"))
            )
            ->orderBy('tgl', 'asc')
            ->get();

        $laporan = [];
        $temp = [
            'tgl_mulai' => null, 
            'masuk' => 0, 
            'keluar' => 0, 
            'data_kayu_masuk' => [], 
            'data_hasil_produksi' => []
        ];
        $saldo = 0;
        $toleransi = 0.01;

        foreach ($history as $row) {
            if ($saldo <= $toleransi && $row->masuk > 0) {
                $temp = [
                    'tgl_mulai' => $row->tgl, 
                    'masuk' => 0, 
                    'keluar' => 0, 
                    'data_kayu_masuk' => [], 
                    'data_hasil_produksi' => []
                ];
            }

            $saldo += $row->masuk;
            $saldo -= $row->keluar;
            
            $temp['masuk'] += $row->masuk;
            $temp['keluar'] += $row->keluar;

            // Simpan detail data ke dalam array sesuai tipenya
            if ($row->tipe === 'MASUK') {
                $temp['data_kayu_masuk'][] = $row;
            } else {
                $temp['data_hasil_produksi'][] = $row;
            }

            if ($saldo <= $toleransi && $temp['masuk'] > 0) {
                $laporan[] = array_merge($temp, ['status' => 'HABIS']);
                $saldo = 0; 
                $temp = ['tgl_mulai' => null, 'masuk' => 0, 'keluar' => 0, 'data_kayu_masuk' => [], 'data_hasil_produksi' => []];
            }
        }

        if ($temp['tgl_mulai'] !== null) {
            $laporan[] = array_merge($temp, ['status' => 'BELUM HABIS']);
        }

        return $laporan;
    }
}