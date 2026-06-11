<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KendalaKedi extends Model
{
    protected $table = 'kendala_kedis';

    protected $fillable = [
        'produksi_kedi_id',
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

    public function produksiKedi(): BelongsTo
    {
        return $this->belongsTo(ProduksiKedi::class, 'produksi_kedi_id');
    }

    public function mesin(): BelongsTo
    {
        return $this->belongsTo(Mesin::class, 'mesin_id');
    }
}
