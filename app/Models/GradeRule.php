<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeRule extends Model
{
    protected $fillable = [
        'grade_id',
        'criterion_id',
        'kondisi',
        'penjelasan',
        'poin_lulus',
        'poin_parsial',
    ];

    protected $casts = [
        'poin_lulus'   => 'float',
        'poin_parsial' => 'float',
    ];

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    /**
     * Criterion (pertanyaan) yang aturan ini berlaku untuknya.
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criteria::class);
    }

    // ── Business Logic ────────────────────────────────────────────────────────

    /**
     * Hitung poin berdasarkan jawaban pengawas.
     *
     * Ini adalah SATU-SATUNYA tempat logika penilaian berada.
     * InferenceEngine memanggil method ini untuk setiap jawaban.
     *
     * @param  string  $jawaban  'ya' atau 'tidak'
     * @return float   Poin yang diperoleh (0 sampai poin_lulus)
     */
    public function pointsFor(string $jawaban): float
    {
        // Tidak ada cacat → selalu lulus penuh di semua grade
        if ($jawaban === 'tidak') {
            return (float) $this->poin_lulus;
        }

        // Ada cacat → tergantung kondisi aturan grade ini
        return match ($this->kondisi) {
            // Cacat sama sekali tidak boleh ada → gagal total
            'not_allowed' => 0.0,

            // Cacat boleh ada dengan batasan → poin parsial
            'conditional' => (float) $this->poin_parsial,

            // Cacat diizinkan sepenuhnya untuk grade ini → poin penuh
            'allowed'     => (float) $this->poin_lulus,

            default       => 0.0,
        };
    }
}
