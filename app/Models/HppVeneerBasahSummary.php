<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppVeneerBasahSummary extends Model
{
    protected $table = 'hpp_veneer_basah_summaries';

    protected $fillable = [
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'stok_lembar',
        'stok_kubikasi',
        'nilai_stok',
        'hpp_average',
        'hpp_kayu_last',
        'hpp_pekerja_last',
        'hpp_mesin_last',
        'hpp_bahan_penolong_last',
        'id_last_log',
    ];

    protected $casts = [
        'stok_kubikasi'          => 'float',
        'nilai_stok'             => 'float',
        'hpp_average'            => 'float',
        'hpp_kayu_last'          => 'float',
        'hpp_pekerja_last'       => 'float',
        'hpp_mesin_last'         => 'float',
        'hpp_bahan_penolong_last'=> 'float',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(HppVeneerBasahLog::class, 'id_last_log');
    }
}