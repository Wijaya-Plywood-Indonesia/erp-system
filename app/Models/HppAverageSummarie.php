<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppAverageSummarie extends Model
{
    protected $table = 'hpp_average_summaries';

    protected $fillable = [
        'id_lahan',
        'id_jenis_kayu',
        'grade',
        'panjang',
        'stok_batang',
        'stok_kubikasi',
        'nilai_stok',
        'hpp_average',
        'id_last_log',
    ];

    protected $casts = [
        'id_lahan'      => 'integer',
        'id_jenis_kayu' => 'integer',
        'grade'         => 'string',  // 'A', 'B', 'C' — sesuai migration varchar(5)
        'panjang'       => 'integer',
        'stok_batang'   => 'integer',
        'stok_kubikasi' => 'decimal:4',
        'nilai_stok'    => 'decimal:2',
        'hpp_average'   => 'decimal:2',
    ];

    public function lahan(): BelongsTo
    {
        return $this->belongsTo(Lahan::class, 'id_lahan');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(HppAverageLog::class, 'id_last_log');
    }

    // App\Models\HppAverageSummarie.php

    public static function forKombinasi(int $lahanId, int $jenisKayuId, int $panjang): ?self
    {
        // ← Guard di sini, SEBELUM firstOrCreate
        if (! \App\Models\JenisKayu::where('id', $jenisKayuId)->exists()) {
            \Illuminate\Support\Facades\Log::warning(
                "HppAverageSummarie::forKombinasi — skip: jenis_kayu_id={$jenisKayuId} tidak ada di master"
            );
            return null;  // ← return null, firstOrCreate tidak dipanggil
        }

        return static::firstOrCreate(
            [
                'id_lahan'      => $lahanId,
                'id_jenis_kayu' => $jenisKayuId,
                'panjang'       => $panjang,
                'grade'         => null,
            ],
            [
                'stok_batang'   => 0,
                'stok_kubikasi' => 0,
                'nilai_stok'    => 0,
                'hpp_average'   => 0,
            ]
        );
    }

    public function getNilaiStokRupiahAttribute(): string
    {
        return 'Rp ' . number_format($this->nilai_stok, 0, ',', '.');
    }

    public function getHppAverageRupiahAttribute(): string
    {
        return 'Rp ' . number_format($this->hpp_average, 0, ',', '.');
    }
}
