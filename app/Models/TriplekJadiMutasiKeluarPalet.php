<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TriplekJadiMutasiKeluarPalet extends Model
{
    protected $table = 'triplek_jadi_mutasi_keluar_palets';

    protected $fillable = [
        'id_mutasi_keluar',
        'nomor_palet',
        'jumlah_lembar',
    ];

    protected $casts = [
        'nomor_palet'   => 'integer',
        'jumlah_lembar' => 'integer',
    ];

    public function mutasiKeluar(): BelongsTo
    {
        return $this->belongsTo(TriplekJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }
}
