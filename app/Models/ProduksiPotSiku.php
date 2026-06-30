<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiPotSiku extends Model
{
    protected $table = 'produksi_pot_siku';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiPotSiku()
    {
        return $this->hasMany(PegawaiPotSiku::class, 'id_produksi_pot_siku');
    }

    public function detailBarangDikerjakanPotSiku()
    {
        return $this->hasMany(DetailBarangDikerjakanPotSiku::class, 'id_produksi_pot_siku');
    }

    public function validasiPotSiku()
    {
        return $this->hasMany(ValidasiPotSiku::class, 'id_produksi_pot_siku');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiPotSiku::class, 'id_produksi_pot_siku')->latestOfMany();
    }
}
