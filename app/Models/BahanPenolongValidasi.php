<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class BahanPenolongValidasi extends Model
{
    protected $table = 'bahan_penolong_validasi';

    protected $fillable = [
        'produksi_type',
        'produksi_id',
        'divalidasi_at',
        'divalidasi_oleh',
    ];

    protected $casts = [
        'divalidasi_at' => 'datetime',
    ];

    public function produksi(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'produksi_type', 'produksi_id');
    }

    public function penvalidasi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }

    /**
     * Helper statis: cek apakah bahan penolong sebuah produksi sudah divalidasi.
     */
    public static function sudahDivalidasi(string $produksiType, int $produksiId): bool
    {
        return static::where('produksi_type', $produksiType)
            ->where('produksi_id', $produksiId)
            ->exists();
    }
}