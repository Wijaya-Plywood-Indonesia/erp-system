<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HargaKayuLog extends Model
{
    protected $fillable = [
        'id_harga_kayu',
        'harga_lama',
        'harga_baru',
        'petugas',
        'aksi'
    ];

    /**
     * Relasi balik ke data master harga kayu
     */
    public function hargaKayu(): BelongsTo
    {
        return $this->belongsTo(HargaKayu::class, 'id_harga_kayu');
    }
}
