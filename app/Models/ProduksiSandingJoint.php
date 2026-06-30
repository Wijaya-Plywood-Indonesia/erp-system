<?php

namespace App\Models;

use App\Filament\Resources\ValidasiSandings\Schemas\ValidasiSandingForm;
use Illuminate\Database\Eloquent\Model;

class ProduksiSandingJoint extends Model
{
    protected $table = 'produksi_sanding_joint';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiSandingJoint()
    {
        return $this->hasMany(PegawaiSandingJoint::class, 'id_produksi_sanding_joint');
    }

    public function hasilSandingJoint()
    {
        return $this->hasMany(HasilSandingJoint::class, 'id_produksi_sanding_joint');
    }

    public function validasiSandingJoint()
    {
        return $this->hasMany(ValidasiSandingJoint::class, 'id_produksi_sanding_joint');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiSandingJoint::class, 'id_produksi_sanding_joint')->latestOfMany();
    }
}
