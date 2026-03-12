<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class HppVeneerBasahLog extends Model
{
    protected $table = 'hpp_veneer_basah_logs';

    protected $fillable = [
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'tanggal',
        'tipe_transaksi',
        'keterangan',
        'referensi_type',
        'referensi_id',
        'total_lembar',
        'total_kubikasi',
        'hpp_kayu',
        'hpp_pekerja',
        'hpp_mesin',
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
        'total_kubikasi'       => 'float',
        'hpp_kayu'             => 'float',
        'hpp_pekerja'          => 'float',
        'hpp_mesin'            => 'float',
        'hpp_bahan_penolong'   => 'float',
        'hpp_average'          => 'float',
        'nilai_stok'           => 'float',
        'stok_kubikasi_before' => 'float',
        'stok_kubikasi_after'  => 'float',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }

    public function bahanPenolong(): HasMany
    {
        return $this->hasMany(HppVeneerBasahBahanPenolong::class, 'id_log');
    }
}