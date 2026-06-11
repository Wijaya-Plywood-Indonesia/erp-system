<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KendalaRotary extends Model
{
    protected $table = 'kendala_rotaries';

    protected $fillable = [
        'produksi_rotary_id',
        'mesin_id',
        'waktu_mulai',
        'kendala',
        'foto_kendala',
        'waktu_selesai',
        'foto_selesai',
        'status',
        'durasi_menit',
    ];

    protected $casts = [
        'waktu_mulai' => 'datetime',
        'waktu_selesai' => 'datetime',
    ];

    public function produksiRotary(): BelongsTo
    {
        return $this->belongsTo(ProduksiRotary::class, 'produksi_rotary_id');
    }

    public function mesin(): BelongsTo
    {
        return $this->belongsTo(Mesin::class, 'mesin_id');
    }
}
