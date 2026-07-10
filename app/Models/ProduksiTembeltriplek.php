<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiTembeltriplek extends Model
{
    protected $table = 'produksi_tembel_triplek';

    protected $fillable = [
        'tanggal',
        'kendala',
    ];

    public function pegawaiTembeltriplek()
    {
        return $this->hasMany(PegawaiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function hasilTembeltriplek()
    {
        return $this->hasMany(HasilTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function validasiTembeltriplek()
    {
        return $this->hasMany(ValidasiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function bahanPenolongTembeltriplek()
    {
        return $this->hasMany(BahanPenolongTembeltriplek::class, 'id_produksi_tembel_triplek');
    }
}
