<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SessionAnswer extends Model
{
    protected $table = 'session_answers';

    public $timestamps = false;

    protected $fillable = [
        'id_session',
        'id_criteria',   // ← kolom FK di tabel session_answers
        'jawaban',
        'answered_at',
    ];

    protected $casts = [
        'answered_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(GradingSession::class, 'id_session');
    }

    /**
     * Relasi ke Criteria dengan FK eksplisit 'id_criteria'.
     *
     * Dipakai di InferenceEngine:
     *   $session->answers()->with('criteria')->get()->keyBy('id_criteria')
     */
    public function criteria(): BelongsTo
    {
        return $this->belongsTo(Criteria::class, 'id_criteria');
    }
}
