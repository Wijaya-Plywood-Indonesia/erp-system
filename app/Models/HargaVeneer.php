<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HargaVeneer extends Model
{
    protected $table = 'harga_veneers';

    protected $fillable = [
        'ukuran',
        'id_jenis_kayu',
        'harga_basah',
        'harga_kering',
        'harga_jadi',
    ];

    protected $casts = [
        'harga_basah' => 'integer',
        'harga_kering' => 'integer',
        'harga_jadi' => 'integer',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }
}
