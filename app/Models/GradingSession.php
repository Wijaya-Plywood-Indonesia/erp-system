<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GradingSession extends Model
{
    protected $fillable = [
        'id_kategori_barang',
        'user_id',
        'status',
        'hasil_grade_id',
        'persentase_hasil',
        'alasan_utama',
        'durasi_detik',
    ];

    protected $casts = [
        // JSON otomatis di-decode jadi array saat diakses
        'persentase_hasil' => 'array',
    ];

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * Pengawas yang melakukan grading ini.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Kategori barang yang sedang dinilai.
     * Menggunakan model KategoriBarang yang sudah ada.
     */
    public function kategoriBarang(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'id_kategori_barang');
    }

    /**
     * Grade yang direkomendasikan sistem setelah inferensi.
     * Menggunakan model Grade yang sudah ada.
     */
    public function hasilGrade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'hasil_grade_id');
    }

    /**
     * Semua jawaban yang diberikan dalam sesi ini.
     * Ini yang dibaca InferenceEngine.
     */
    public function answers(): HasMany
    {
        return $this->hasMany(SessionAnswer::class, 'session_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Nama grade dengan persentase tertinggi dari hasil inferensi.
     * Digunakan untuk tampilan ringkas di tabel riwayat.
     */
    public function getTopGradeNameAttribute(): string
    {
        if (empty($this->persentase_hasil)) return '—';

        return collect($this->persentase_hasil)
            ->sortDesc()
            ->keys()
            ->first() ?? '—';
    }

    /**
     * Persentase tertinggi dari semua grade.
     */
    public function getTopPercentageAttribute(): float
    {
        if (empty($this->persentase_hasil)) return 0.0;

        return (float) collect($this->persentase_hasil)->max();
    }

    /**
     * Apakah sesi ini sudah selesai?
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }
}
