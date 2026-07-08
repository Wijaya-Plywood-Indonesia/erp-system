<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaPlatformJadi extends Model
{
    protected $table = 'serah_terima_platform_jadi';

    protected $fillable = [
        'id_hasil_sanding',
        'diterima_by',
        'diterima_at',
        'keterangan',
    ];

    protected $casts = [
        'diterima_at' => 'datetime',
    ];

    public function hasilSanding(): BelongsTo
    {
        return $this->belongsTo(HasilSanding::class, 'id_hasil_sanding');
    }

    public function penerima(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }
}