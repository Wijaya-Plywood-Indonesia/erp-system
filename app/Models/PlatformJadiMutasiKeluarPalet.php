<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformJadiMutasiKeluarPalet extends Model
{
    protected $table = 'platform_jadi_mutasi_keluar_palets';

    protected $fillable = [
        'id_mutasi_keluar',
        'nomor_palet',
        'jumlah_lembar',
    ];

    public function mutasiKeluar()
    {
        return $this->belongsTo(PlatformJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }
}