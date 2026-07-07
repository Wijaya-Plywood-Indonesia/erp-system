<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerJadiMutasiKeluarPalet extends Model
{
    protected $fillable = [
        'id_mutasi_keluar',
        'nomor_palet',
        'jumlah_lembar'
    ];
    public function mutasiKeluar()
    {
        return $this->belongsTo(VeneerJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }
}
