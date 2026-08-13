<?php

namespace App\Models;

use App\Traits\HasRouteUuid;
use Illuminate\Database\Eloquent\Model;

class produksi_guellotine extends Model
{
    use HasRouteUuid;
    protected $table = 'produksi_guellotine';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
        'uuid',
    ];

    public function pegawaiGuellotine()
    {
        return $this->hasMany(pegawai_guellotine::class, 'id_produksi_guellotine');
    }

    public function hasilGuellotine()
    {
        return $this->hasMany(hasil_guellotine::class, 'id_produksi_guellotine');
    }

    public function validasiGuellotine()
    {
        return $this->hasMany(validasi_guellotine::class, 'id_produksi_guellotine');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(validasi_guellotine::class, 'id_produksi_guellotine')->latestOfMany();
    }
}
