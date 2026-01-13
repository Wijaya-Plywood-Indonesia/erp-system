<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiPilihPlywood extends Model
{
    protected $table = 'produksi_pilih_plywood';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiPilihPlywood()
    {
        return $this->hasMany(PegawaiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function bahanPilihPlywood()
    {
        return $this->hasMany(BahanPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function hasilPilihPlywood()
    {
        return $this->hasMany(HasilPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function listPekerjaanMenumpuk()
    {
        return $this->hasMany(ListPekerjaanMenumpuk::class, 'id_produksi_pilih_plywood');
    }

    public function validasiPilihPlywood()
    {
        return $this->hasMany(ValidasiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiPilihPlywood::class, 'id_produksi_pilih_plywood')->latestOfMany();
    }
}
