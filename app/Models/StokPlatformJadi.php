<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokPlatformJadi extends Model
{
    protected $table = 'stok_platform_jadi';

    protected $fillable = [
        'id_jenis_barang',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'stok_lembar',
        'stok_kubikasi',
        'nilai_stok',
        'hpp_average',
        'hpp_pekerja_last',
        'hpp_bahan_penolong_last',
        'id_last_log',
    ];

    protected $casts = [
        'panjang'                 => 'float',
        'lebar'                   => 'float',
        'tebal'                   => 'float',
        'stok_kubikasi'           => 'float',
        'nilai_stok'              => 'float',
        'hpp_average'             => 'float',
        'hpp_pekerja_last'        => 'float',
        'hpp_bahan_penolong_last' => 'float',
    ];

    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarang::class, 'id_jenis_barang');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(HppPlatformJadiLog::class, 'id_last_log');
    }
}