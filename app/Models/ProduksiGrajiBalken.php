<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiGrajiBalken extends Model
{
    protected $table = 'produksi_graji_balken';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiGrajiBalken()
    {
        return $this->hasMany(PegawaiGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function hasilGrajiBalken()
    {
        return $this->hasMany(HasilGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function validasiGrajiBalken()
    {
        return $this->hasMany(ValidasiGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiGrajiBalken::class, 'id_produksi_graji_balken')->latestOfMany();
    }
}
