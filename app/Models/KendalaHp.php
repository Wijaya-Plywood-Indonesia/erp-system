<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KendalaHp extends Model
{
    protected $table = 'kendala_hps';

    protected $fillable = [
        'produksi_hp_id',
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

    public function produksiHp(): BelongsTo
    {
        return $this->belongsTo(ProduksiHp::class, 'produksi_hp_id');
    }

    public function mesin(): BelongsTo
    {
        return $this->belongsTo(Mesin::class, 'mesin_id');
    }
}
