<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class GudangTriplekJadi extends Model
{
    protected $table = 'gudang_triplek_jadis';

    // Sumber antrean — pakai konstanta supaya tidak ada string mentah tersebar
    public const SOURCE_PRODUKSI = 'produksi';
    public const SOURCE_REPAIR   = 'repair';
    public const SOURCE_BM       = 'bm';

    public const STATUS_BELUM_DITERIMA = 'belum diterima';
    public const STATUS_SUDAH_DITERIMA = 'sudah diterima';

    protected $fillable = [
        'source',
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'stok_lembar',
        'stok_kubikasi',
        'nilai_stok',
        'hpp_pekerja_last',
        'hpp_bahan_penolong_last',
        'referensi_type',
        'referensi_id',
        'keterangan',
        'status_gudang',
        'diterima_by',
        'diterima_at',
    ];

    protected $casts = [
        'panjang'                 => 'float',
        'lebar'                   => 'float',
        'tebal'                   => 'float',
        'stok_lembar'             => 'integer',
        'stok_kubikasi'           => 'float',
        'nilai_stok'              => 'float',
        'hpp_pekerja_last'        => 'float',
        'hpp_bahan_penolong_last' => 'float',
        'diterima_at'             => 'datetime',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }

    /** Sumber hulu (ProduksiTriplek, Repair, Nota BM, dll.) */
    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }
}
