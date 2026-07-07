<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GudangVeneerJadi extends Model
{
    protected $table = 'gudang_veneer_jadis';

    protected $fillable = [
        'id_jenis_kayu',
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
        'status_gudang',
        'keterangan',
        'diterima_by',
        'diterima_at',
    ];

    protected $casts = [
        'stok_kubikasi'          => 'float',
        'nilai_stok'             => 'float',
        'hpp_average'            => 'float',
        'hpp_pekerja_last'       => 'float',
        'hpp_bahan_penolong_last' => 'float',
        'diterima_at'             => 'datetime',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(HppVeneerJadiLog::class, 'id_last_log');
    }
    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }
}
