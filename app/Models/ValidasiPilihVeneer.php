<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiPilihVeneer extends Model
{
    protected $table = 'validasi_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'role',
        'status',
    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }
}
