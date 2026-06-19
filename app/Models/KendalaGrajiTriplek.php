<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KendalaGrajiTriplek extends Model
{
    protected $table = 'kendala_graji_tripleks';

    protected $fillable = [
        'produksi_graji_triplek_id',
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

    public function produksiGrajiTriplek(): BelongsTo
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'produksi_graji_triplek_id');
    }

    public function mesin(): BelongsTo
    {
        return $this->belongsTo(Mesin::class, 'mesin_id');
    }
}
