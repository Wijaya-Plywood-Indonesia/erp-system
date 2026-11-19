<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HppTriplekJadiLog extends Model
{
    protected $table = 'hpp_triplek_jadi_log';

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
        'stok_lembar_before'   => 'integer',
        'stok_lembar_after'    => 'integer',
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

    public const SUMMARY_CACHE_VERSION_KEY = 'hpp_triplek_jadi_log.summary.version';

    protected static function booted(): void
    {
        static::saved(fn() => self::flushSummaryCache());
        static::deleted(fn() => self::flushSummaryCache());
    }

    /** Naikkan versi cache. Dijalankan setelah commit agar tidak menyimpan data basi. */
    public static function flushSummaryCache(): void
    {
        DB::afterCommit(
            fn() => Cache::forever(self::SUMMARY_CACHE_VERSION_KEY, (string) hrtime(true))
        );
    }
}
