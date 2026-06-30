<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class KayuComparator
{
    public static function buildQuery($notaId)
    {
        $left = DB::table('detail_kayu as d')
            ->select([
                'd.id_jenis_kayu',
                'd.id_lahan',
                'd.panjang',
                'd.diameter',
                'd.grade',
                DB::raw('SUM(d.jumlah) as total_detail_jumlah'),
                DB::raw('0 as total_turusan_jumlah'),
            ])
            ->where('d.id_nota_kayu', $notaId)
            ->groupBy('d.id_jenis_kayu', 'd.id_lahan', 'd.panjang', 'd.diameter', 'd.grade');

        $right = DB::table('turusan_kayu as t')
            ->select([
                't.id_jenis_kayu',
                't.id_lahan',
                't.panjang',
                't.diameter',
                't.grade',
                DB::raw('0 as total_detail_jumlah'),
                DB::raw('SUM(t.jumlah) as total_turusan_jumlah'),
            ])
            ->where('t.id_nota_kayu', $notaId)
            ->groupBy('t.id_jenis_kayu', 't.id_lahan', 't.panjang', 't.diameter', 't.grade');

        return DB::query()
            ->fromSub($left->unionAll($right), 'x')
            ->select([
                'x.*',
                DB::raw('ABS(total_detail_jumlah - total_turusan_jumlah) as selisih')
            ])
            ->groupBy(
                'x.id_jenis_kayu',
                'x.id_lahan',
                'x.panjang',
                'x.diameter',
                'x.grade'
            );
    }
}
