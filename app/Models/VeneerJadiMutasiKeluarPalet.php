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
}
