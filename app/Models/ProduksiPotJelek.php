<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiPotJelek extends Model
{
    protected $table = 'produksi_pot_jelek';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiPotJelek()
    {
        return $this->hasMany(PegawaiPotJelek::class, 'id_produksi_pot_jelek');
    }

    public function detailBarangDikerjakanPotJelek()
    {
        return $this->hasMany(DetailBarangDikerjakanPotJelek::class, 'id_produksi_pot_jelek');
    }

    public function validasiPotJelek()
    {
        return $this->hasMany(ValidasiPotJelek::class, 'id_produksi_pot_jelek');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiPotJelek::class, 'id_produksi_pot_jelek')->latestOfMany();
    }
}
