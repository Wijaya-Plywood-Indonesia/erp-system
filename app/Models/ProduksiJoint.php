<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiJoint extends Model
{
    protected $table = 'produksi_joint';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiJoint()
    {
        return $this->hasMany(PegawaiJoint::class, 'id_produksi_joint');
    }

    public function modalJoint()
    {
        return $this->hasMany(ModalJoint::class, 'id_produksi_joint');
    }

    public function hasilJoint()
    {
        return $this->hasMany(HasilJoint::class, 'id_produksi_joint');
    }

    public function bahanProduksi()
    {
        return $this->hasMany(BahanProduksi::class, 'id_produksi_joint');
    }

    public function validasiJoint()
    {
        return $this->hasMany(ValidasiJoint::class, 'id_produksi_joint');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiJoint::class, 'id_produksi_joint')->latestOfMany();
    }
}
