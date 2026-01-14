<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiSanding extends Model
{
    //

    protected $table = 'produksi_sandings';

    protected $fillable = [
        'tanggal',
        'id_mesin',
        'kendala',
        'shift',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];
    public function modalSandings()
    {
        return $this->hasMany(ModalSanding::class, 'id_produksi_sanding');
    }
    public function hasilSandings()
    {
        return $this->hasMany(HasilSanding::class, 'id_produksi_sanding');
    }
    public function pegawaiSandings()
    {
        return $this->hasMany(PegawaiSanding::class, 'id_produksi_sanding');
    }
    public function validasiSanding()
    {
        return $this->hasMany(ValidasiSanding::class, 'id_produksi_sanding');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiSanding::class, 'id_produksi_sanding')->latestOfMany();
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }
}
