<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformMthMutasiDetail extends Model
{
    protected $table = 'platform_mth_mutasi_details';

    protected $fillable = [
        'id_platform_mth_mutasi', 'id_ukuran', 'id_jenis_kayu',
        'kw_grade', 'qty', 'm3',
    ];

    protected $casts = [
        'qty' => 'integer',
        'm3' => 'float',
    ];

    public const M3_DIVISOR = 10_000_000;

    public static function hitungM3(Ukuran $u, int $qty): float
    {
        return ($u->panjang * $u->lebar * $u->tebal * $qty) / self::M3_DIVISOR;
    }

    public function mutasi(): BelongsTo
    {
        return $this->belongsTo(PlatformMthMutasi::class, 'id_platform_mth_mutasi');
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }
}
