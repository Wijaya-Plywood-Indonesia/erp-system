<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiPilihVeneer extends Model
{
    protected $table = 'produksi_pilih_veneer';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiPilihVeneer()
    {
        return $this->hasMany(PegawaiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function modalPilihVeneer()
    {
        return $this->hasMany(ModalPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function hasilPilihVeneer()
    {
        return $this->hasMany(HasilPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function validasiPilihVeneer()
    {
        return $this->hasMany(ValidasiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiPilihVeneer::class, 'id_produksi_pilih_veneer')->latestOfMany();
    }
}
