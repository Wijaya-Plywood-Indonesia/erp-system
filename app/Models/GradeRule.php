<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeRule extends Model
{
    protected $table = 'grade_rules';

    protected $fillable = [
        'id_grade',
        'id_criteria',
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
        return $this->belongsTo(Grade::class, 'id_grade');
    }

    /**
     * FIX: Tambahkan 'id_criteria' sebagai foreign key eksplisit.
     */
    public function criteria(): BelongsTo
    {
        return $this->belongsTo(Criteria::class, 'id_criteria');
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
