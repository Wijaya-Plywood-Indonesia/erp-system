<?php

namespace App\Models;

use App\Services\HppAverageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;

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
        'stok_kubikasi' => 'decimal:6',
        'nilai_stok'    => 'decimal:2',
        'hpp_average'   => 'decimal:2',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

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

    // =========================================================================
    // SCOPES & QUERY HELPERS
    // =========================================================================

    /**
     * Mendapatkan atau membuat record summary untuk kombinasi tertentu
     * 
     * @param int $lahanId
     * @param int $jenisKayuId
     * @param int $panjang
     * @return self|null
     */
    public static function forKombinasi(int $lahanId, int $jenisKayuId, int $panjang): ?self
    {
        // Validasi master data sebelum membuat record
        if (!Lahan::where('id', $lahanId)->exists()) {
            Log::warning("[HppAverageSummarie] forKombinasi — skip: lahan_id={$lahanId} tidak ada di master");
            return null;
        }

        if (!JenisKayu::where('id', $jenisKayuId)->exists()) {
            Log::warning("[HppAverageSummarie] forKombinasi — skip: jenis_kayu_id={$jenisKayuId} tidak ada di master");
            return null;
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

    /**
     * Scope untuk mendapatkan stok yang masih tersedia
     */
    public function scopeAvailable($query)
    {
        return $query->where('stok_batang', '>', 0);
    }

    /**
     * Scope untuk filter berdasarkan lahan
     */
    public function scopeByLahan($query, int $lahanId)
    {
        return $query->where('id_lahan', $lahanId);
    }

    /**
     * Scope untuk filter berdasarkan jenis kayu
     */
    public function scopeByJenisKayu($query, int $jenisKayuId)
    {
        return $query->where('id_jenis_kayu', $jenisKayuId);
    }

    /**
     * Scope untuk filter berdasarkan panjang
     */
    public function scopeByPanjang($query, int $panjang)
    {
        return $query->where('panjang', $panjang);
    }

    // =========================================================================
    // ACCESSORS & MUTATORS
    // =========================================================================

    /**
     * Format nilai stok ke Rupiah
     */
    public function getNilaiStokRupiahAttribute(): string
    {
        return 'Rp ' . number_format($this->nilai_stok, 0, ',', '.');
    }

    /**
     * Format HPP Average ke Rupiah
     */
    public function getHppAverageRupiahAttribute(): string
    {
        return 'Rp ' . number_format($this->hpp_average, 0, ',', '.');
    }

    /**
     * Format stok kubikasi dengan 4 desimal
     */
    public function getStokKubikasiFormattedAttribute(): string
    {
        return number_format($this->stok_kubikasi, 4, ',', '.');
    }

    /**
     * Mendapatkan total nilai stok dalam bentuk float yang sudah dibulatkan
     */
    public function getTotalNilaiAttribute(): float
    {
        return round($this->stok_kubikasi * $this->hpp_average, 2);
    }

    // =========================================================================
    // BUSINESS LOGIC
    // =========================================================================

    /**
     * Update stok dengan penambahan
     * 
     * @param int $batang
     * @param float $kubikasi
     * @param float $nilai
     * @param int|null $logId
     * @return bool
     */
    public function tambahStok(int $batang, float $kubikasi, float $nilai, ?int $logId = null): bool
    {
        $this->stok_batang += $batang;
        $this->stok_kubikasi = round($this->stok_kubikasi + $kubikasi, 4);
        $this->nilai_stok = round($this->nilai_stok + $nilai, 2);

        // Recalculate HPP
        if ($this->stok_kubikasi > 0) {
            $this->hpp_average = round($this->nilai_stok / $this->stok_kubikasi, 2);
        }

        if ($logId) {
            $this->id_last_log = $logId;
        }

        $saved = $this->save();

        if ($saved) {
            $this->syncTempatKayu();
        }

        return $saved;
    }

    /**
     * Update stok dengan pengurangan
     * 
     * @param int $batang
     * @param float $kubikasi
     * @param float $nilai
     * @param int|null $logId
     * @return bool
     */
    public function kurangiStok(int $batang, float $kubikasi, float $nilai, ?int $logId = null): bool
    {
        $this->stok_batang = max(0, $this->stok_batang - $batang);
        $this->stok_kubikasi = max(0, round($this->stok_kubikasi - $kubikasi, 4));
        $this->nilai_stok = max(0, round($this->nilai_stok - $nilai, 2));

        // Recalculate HPP
        if ($this->stok_kubikasi > 0) {
            $this->hpp_average = round($this->nilai_stok / $this->stok_kubikasi, 2);
        } else {
            $this->hpp_average = 0;
        }

        if ($logId) {
            $this->id_last_log = $logId;
        }

        $saved = $this->save();

        if ($saved) {
            $this->syncTempatKayu();
        }

        return $saved;
    }

    /**
     * Reset stok menjadi nol
     * 
     * @param int|null $logId
     * @return bool
     */
    public function resetStok(?int $logId = null): bool
    {
        $this->stok_batang = 0;
        $this->stok_kubikasi = 0;
        $this->nilai_stok = 0;
        $this->hpp_average = 0;

        if ($logId) {
            $this->id_last_log = $logId;
        }

        $saved = $this->save();

        if ($saved) {
            $this->syncTempatKayu();
        }

        return $saved;
    }

    /**
     * Recalculate HPP berdasarkan nilai dan kubikasi saat ini
     * 
     * @return float
     */
    public function recalculateHpp(): float
    {
        if ($this->stok_kubikasi > 0) {
            $this->hpp_average = round($this->nilai_stok / $this->stok_kubikasi, 2);
        } else {
            $this->hpp_average = 0;
        }

        $this->saveQuietly(); // Save without triggering events

        return $this->hpp_average;
    }

    // =========================================================================
    // SYNC TEMPAT KAYU (IMPROVED)
    // =========================================================================

    /**
     * Sinkronisasi ke tabel TempatKayu
     * Method ini akan menghitung total stok di lahan dan mengupdate TempatKayu
     * 
     * @return bool
     */
    public function syncTempatKayu(): bool
    {
        try {
            // Validasi lahan
            if (!$this->id_lahan) {
                Log::warning('[TempatKayu] syncTempatKayu SKIP — id_lahan null');
                return false;
            }

            // Cari KayuMasuk terbaru yang terkait lahan ini
            // sebagai foreign key untuk TempatKayu
            $kayuMasuk = KayuMasuk::whereHas('detailTurusanKayus', function ($q) {
                $q->where('lahan_id', $this->id_lahan);
            })
                ->latest('tgl_kayu_masuk')
                ->first();

            // Jika tidak ada KayuMasuk, coba cari berdasarkan id_kayu_masuk di detail
            if (!$kayuMasuk) {
                $kayuMasuk = KayuMasuk::whereHas('detailTurusanKayus', function ($q) {
                    $q->where('lahan_id', $this->id_lahan);
                })->first();
            }

            // Total batang = jumlah dari SEMUA kombinasi di lahan ini
            // bukan hanya kombinasi jenis_kayu + panjang yang baru diubah
            $totalBatang = static::where('id_lahan', $this->id_lahan)
                ->whereNull('grade')
                ->sum('stok_batang');

            $totalKubikasi = static::where('id_lahan', $this->id_lahan)
                ->whereNull('grade')
                ->sum('stok_kubikasi');

            if ($totalBatang == 0) {
                // Jika tidak ada stok, hapus TempatKayu
                $deleted = TempatKayu::where('id_lahan', $this->id_lahan)->delete();

                Log::info('[TempatKayu] sync — stok habis, hapus record', [
                    'id_lahan' => $this->id_lahan,
                    'deleted' => $deleted
                ]);

                return true;
            }

            if (!$kayuMasuk) {
                Log::warning('[TempatKayu] syncTempatKayu SKIP — tidak ada KayuMasuk untuk lahan', [
                    'id_lahan' => $this->id_lahan,
                ]);
                return false;
            }

            // Update atau create TempatKayu
            $tempatKayu = TempatKayu::updateOrCreate(
                [
                    'id_lahan'      => $this->id_lahan,
                    'id_kayu_masuk' => $kayuMasuk->id,
                ],
                [
                    'jumlah_batang' => (int) $totalBatang,
                ]
            );

            Log::info('[TempatKayu] sync berhasil', [
                'id_lahan'       => $this->id_lahan,
                'id_kayu_masuk'  => $kayuMasuk->id,
                'tempat_kayu_id' => $tempatKayu->id,
                'jumlah_batang'  => $totalBatang,
                'total_kubikasi' => $totalKubikasi,
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('[TempatKayu] sync gagal', [
                'id_lahan' => $this->id_lahan,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString()
            ]);

            return false;
        }
    }

    /**
     * Sinkronisasi semua lahan yang memiliki stok
     * 
     * @return array
     */
    public static function syncAllTempatKayu(): array
    {
        $results = [
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        $lahans = static::where('stok_batang', '>', 0)
            ->distinct()
            ->pluck('id_lahan');

        foreach ($lahans as $lahanId) {
            $summary = static::where('id_lahan', $lahanId)->first();
            if ($summary) {
                $success = $summary->syncTempatKayu();
                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }
                $results['details'][$lahanId] = $success;
            }
        }

        Log::info('[TempatKayu] syncAll selesai', $results);

        return $results;
    }

    // =========================================================================
    // EVENT HANDLERS
    // =========================================================================

    /**
     * Boot the model and register event listeners
     */
    protected static function booted()
    {
        // Auto-sync TempatKayu setelah save (kecuali sedang dalam proses sync)
        static::saved(function ($model) {
            // Cegah infinite loop
            if (!$model->getAttribute('_syncing')) {
                $model->setAttribute('_syncing', true);
                $model->syncTempatKayu();
                $model->setAttribute('_syncing', false);
            }
        });

        // Auto-sync TempatKayu setelah delete
        static::deleted(function ($model) {
            // Cek apakah masih ada stok lain di lahan ini
            $remainingStok = static::where('id_lahan', $model->id_lahan)
                ->where('stok_batang', '>', 0)
                ->exists();

            if (!$remainingStok) {
                // Hapus TempatKayu jika tidak ada stok tersisa
                TempatKayu::where('id_lahan', $model->id_lahan)->delete();

                Log::info('[TempatKayu] auto-delete karena stok habis', [
                    'id_lahan' => $model->id_lahan
                ]);
            } else {
                // Update TempatKayu dengan stok terbaru
                $firstSummary = static::where('id_lahan', $model->id_lahan)->first();
                if ($firstSummary) {
                    $firstSummary->syncTempatKayu();
                }
            }
        });
    }

    // =========================================================================
    // VALIDATION HELPERS
    // =========================================================================

    /**
     * Check apakah stok mencukupi untuk transaksi keluar
     * 
     * @param int $batang
     * @param float $kubikasi
     * @return bool
     */
    public function isStokCukup(int $batang, float $kubikasi): bool
    {
        return $this->stok_batang >= $batang && $this->stok_kubikasi >= $kubikasi;
    }

    /**
     * Get stok yang tersedia dalam berbagai format
     * 
     * @return array
     */
    public function getStokInfoAttribute(): array
    {
        return [
            'batang' => $this->stok_batang,
            'kubikasi' => $this->stok_kubikasi,
            'kubikasi_formatted' => $this->stok_kubikasi_formatted,
            'nilai' => $this->nilai_stok,
            'nilai_formatted' => $this->nilai_stok_rupiah,
            'hpp' => $this->hpp_average,
            'hpp_formatted' => $this->hpp_average_rupiah,
        ];
    }
}
