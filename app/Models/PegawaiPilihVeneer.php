<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPilihVeneer extends Model
{
    protected $table = 'pegawai_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
        
    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}
