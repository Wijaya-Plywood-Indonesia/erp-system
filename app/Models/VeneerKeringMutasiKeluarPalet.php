<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerKeringMutasiKeluarPalet extends Model
{
    protected $table = 'veneer_kering_mutasi_keluar_palets';

    protected $fillable = [
        'id_mutasi_keluar',
        'no_palet',
        'qty',
    ];

    protected $casts = [
        'no_palet' => 'integer',
        'qty' => 'decimal:4',
    ];

    public function mutasiKeluar()
    {
        return $this->belongsTo(VeneerKeringMutasiKeluar::class, 'id_mutasi_keluar');
    }
}