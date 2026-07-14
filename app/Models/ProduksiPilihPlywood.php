<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

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

    public function serahTerimaTriplekJadi()
    {
        return $this->hasMany(SerahTerimaTriplekJadi::class, 'id_produksi_pilih_plywood');
    }

    /**
     * Barang cacat yang diserahkan, diakses lewat HasilPilihPlywood
     * (serah_terima_triplek_cacat tidak punya FK langsung ke produksi_pilih_plywood).
     */
    public function serahTerimaTriplekCacat(): HasManyThrough
    {
        return $this->hasManyThrough(
            SerahTerimaTriplekCacat::class,
            HasilPilihPlywood::class,
            'id_produksi_pilih_plywood', // FK di hasil_pilih_plywood -> produksi_pilih_plywood
            'id_hasil_pilih_plywood',    // FK di serah_terima_triplek_cacat -> hasil_pilih_plywood
            'id',                        // local key di produksi_pilih_plywood
            'id'                         // local key di hasil_pilih_plywood
        );
    }
}
