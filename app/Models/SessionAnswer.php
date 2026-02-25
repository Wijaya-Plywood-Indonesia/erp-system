<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAnswer extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'session_id',
        'criterion_id',
        'jawaban',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    // ── Relations ────────────────────────────────────────────────────────────

    /**
     * Sesi grading tempat jawaban ini berasal.
     */
    public function session(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class, 'session_id');
    }

    /**
     * Pertanyaan/kriteria yang dijawab.
     */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(Criteria::class);
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    public function isYa(): bool
    {
        return $this->jawaban === 'ya';
    }

    public function isTidak(): bool
    {
        return $this->jawaban === 'tidak';
    }
}
