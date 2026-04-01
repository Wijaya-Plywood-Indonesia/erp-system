<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProduksiKedi extends Model
{
    protected $table = 'produksi_kedi';

    protected $fillable = [
        'tanggal',
        'kendala',
        'kode_kedi',
        'status',
    ];

    public function detailMasukKedi()
    {
        return $this->hasMany(DetailMasukKedi::class, 'id_produksi_kedi');
    }

    public function detailBongkarKedi()
    {
        return $this->hasMany(DetailBongkarKedi::class, 'id_produksi_kedi');
    }

    public function validasiKedi()
    {
        return $this->hasMany(ValidasiKedi::class, 'id_produksi_kedi');
    }

    public function detailPegawaiKedi()
    {
        return $this->hasMany(DetailPegawaiKedi::class, 'id_produksi_kedi');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiKedi::class, 'id_produksi_kedi')->latestOfMany();
    }

    public function serahTerima(): HasManyThrough
    {
        return $this->hasManyThrough(
            SerahTerimaPivot::class,
            DetailHasilPaletRotary::class,
            'id_produksi',
            'id_detail_hasil_palet_rotary',
            'id',
            'id'
        );
    }
}
