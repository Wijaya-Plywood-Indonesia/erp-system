<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HppPlatformJadiLog extends Model
{
    protected $table = 'hpp_platform_jadi_log';

    protected $fillable = [
        'id_jenis_barang',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'tanggal',
        'tipe_transaksi',
        'keterangan',
        'referensi_type',
        'referensi_id',
        'total_lembar',
        'total_kubikasi',
        'hpp_pekerja',
        'hpp_bahan_penolong',
        'hpp_average',
        'nilai_stok',
        'stok_lembar_before',
        'stok_kubikasi_before',
        'nilai_stok_before',
        'stok_lembar_after',
        'stok_kubikasi_after',
        'nilai_stok_after',
    ];

    protected $casts = [
        'tanggal'              => 'date',
        'total_lembar'         => 'integer',
        'total_kubikasi'       => 'float',
        'hpp_pekerja'          => 'float',
        'hpp_bahan_penolong'   => 'float',
        'hpp_average'          => 'float',
        'nilai_stok'           => 'float',
        'stok_kubikasi_before' => 'float',
        'stok_kubikasi_after'  => 'float',
    ];

    public function jenisBarang(): BelongsTo
    {
        return $this->belongsTo(JenisBarang::class, 'id_jenis_barang');
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }
}