<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiGrajitriplek extends Model
{
    protected $table = 'produksi_graji_triplek';

    protected $fillable = [
        'id_produksi_graji_triplek',
        'tanggal_produksi',
        'status',
        'kendala',
        'shift'
    ];

    public function pegawaiGrajiTriplek()
    {
        return $this->hasMany(PegawaiGrajiTriplek::class, 'id_produksi_graji_triplek');
    }

    public function masukGrajiTriplek()
    {
        return $this->hasMany(MasukGrajiTriplek::class, 'id_produksi_graji_triplek');
    }

    public function hasilGrajiTriplek()
    {
        return $this->hasMany(HasilGrajiTriplek::class, 'id_produksi_graji_triplek');
    }

    public function validasiGrajiTriplek()
    {
        return $this->hasMany(ValidasiGrajiTriplek::class, 'id_produksi_graji_triplek');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiGrajiTriplek::class, 'id_produksi_graji_triplek')->latestOfMany();
    }
}
