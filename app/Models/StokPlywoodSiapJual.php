<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokPlywoodSiapJual extends Model
{
    protected $table = 'stok_plywood_siap_jual';

    protected $fillable = [
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'stok_lembar',
        'stok_kubikasi',
        'id_last_log',
    ];

    protected $casts = [
        'stok_kubikasi'          => 'float',
        'nilai_stok'             => 'float',
        'hpp_average'            => 'float',
        'hpp_pekerja_last'       => 'float',
        'hpp_bahan_penolong_last'=> 'float',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(HppPlywoodSiapJualLog::class, 'id_last_log');
    }
}
