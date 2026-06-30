<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KendalaSanding extends Model
{
    protected $table = 'kendala_sandings';

    protected $fillable = [
        'produksi_sanding_id',
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

    public function produksiSanding(): BelongsTo
    {
        return $this->belongsTo(ProduksiSanding::class, 'produksi_sanding_id');
    }

    public function mesin(): BelongsTo
    {
        return $this->belongsTo(Mesin::class, 'mesin_id');
    }
}
