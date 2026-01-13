<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiPotAfJoint extends Model
{
    protected $table = 'produksi_pot_af_joint';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiPotAfJoint()
    {
        return $this->hasMany(PegawaiPotAfJoint::class, 'id_produksi_pot_af_joint');
    }

    public function hasilPotAfJoint()
    {
        return $this->hasMany(HasilPotAfJoint::class, 'id_produksi_pot_af_joint');
    }

    public function validasiPotAfJoint()
    {
        return $this->hasMany(ValidasiPotAfJoint::class, 'id_produksi_pot_af_joint');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiPotAfJoint::class, 'id_produksi_pot_af_joint')->latestOfMany();
    }
}
