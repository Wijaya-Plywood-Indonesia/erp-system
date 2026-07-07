<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;

class HppPlywoodSiapJualLog extends Model
{
    protected $table = 'hpp_plywood_siap_jual_log';

    protected $fillable = [
        'id_jenis_kayu',
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
        'stok_lembar_before',
        'stok_kubikasi_before',
        'stok_lembar_after',
        'stok_kubikasi_after',
    ];

    protected $casts = [
        'tanggal'              => 'date',
        'total_lembar'         => 'integer',
        'total_kubikasi'       => 'float',
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
}
