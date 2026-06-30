<?php

namespace App\Services;

use App\Models\NotaKayu;
use Illuminate\Database\Eloquent\Builder;

class PersentaseKayuService
{
    public static function query(string $bulan): Builder
    {
        [$year, $month] = explode('-', $bulan);

        return NotaKayu::query()
            ->join('kayu_masuks', 'kayu_masuks.id', '=', 'nota_kayus.id_kayu_masuk')
            ->join('detail_kayu_masuks', 'detail_kayu_masuks.id_kayu_masuk', '=', 'kayu_masuks.id')
            ->join('lahans', 'lahans.id', '=', 'detail_kayu_masuks.id_lahan')

            // 🔑 JOIN HARGA KAYU (INI KUNCI)
            ->leftJoin('harga_kayus', function ($join) {
                $join->on('harga_kayus.id_jenis_kayu', '=', 'detail_kayu_masuks.id_jenis_kayu')
                    ->on('harga_kayus.grade', '=', 'detail_kayu_masuks.grade')
                    ->on('harga_kayus.panjang', '=', 'detail_kayu_masuks.panjang')
                    ->whereRaw('detail_kayu_masuks.diameter BETWEEN harga_kayus.diameter_terkecil AND harga_kayus.diameter_terbesar');
            })

            // 🔗 PRODUKSI ROTARY
            ->leftJoin('produksi_rotaries', function ($join) use ($month, $year) {
                $join->whereMonth('produksi_rotaries.tgl_produksi', $month)
                     ->whereYear('produksi_rotaries.tgl_produksi', $year);
            })
            ->leftJoin(
                'detail_hasil_palet_rotaries',
                'detail_hasil_palet_rotaries.id_produksi',
                '=',
                'produksi_rotaries.id'
            )

            ->whereMonth('kayu_masuks.tgl_kayu_masuk', $month)
            ->whereYear('kayu_masuks.tgl_kayu_masuk', $year)
            ->whereNotNull('nota_kayus.id')

            ->groupBy(
                'nota_kayus.id',
                'kayu_masuks.tgl_kayu_masuk',
                'lahans.nama_lahan'
            )

            ->selectRaw('
                nota_kayus.id as nota_id,
                DATE(kayu_masuks.tgl_kayu_masuk) as tanggal,
                lahans.nama_lahan as lahan,

                SUM(detail_kayu_masuks.jumlah_batang) as total_batang,

                SUM(
                    detail_kayu_masuks.panjang *
                    detail_kayu_masuks.diameter *
                    detail_kayu_masuks.diameter *
                    detail_kayu_masuks.jumlah_batang *
                    0.785 / 1000000
                ) as kubikasi_kayu,

                SUM(
                    harga_kayus.harga_beli *
                    (
                        detail_kayu_masuks.panjang *
                        detail_kayu_masuks.diameter *
                        detail_kayu_masuks.diameter *
                        detail_kayu_masuks.jumlah_batang *
                        0.785 / 1000000
                    ) * 1000
                ) as poin,

                COALESCE(
                    SUM(
                        detail_hasil_palet_rotaries.panjang *
                        detail_hasil_palet_rotaries.lebar *
                        detail_hasil_palet_rotaries.tebal *
                        detail_hasil_palet_rotaries.jumlah
                        / 10000000
                    ), 0
                ) as kubikasi_veneer
            ')
            ->selectRaw('
                (poin / NULLIF(kubikasi_veneer, 0)) as harga_veneer_per_m3,
                poin as total_harga_veneer
            ');
    }
}
