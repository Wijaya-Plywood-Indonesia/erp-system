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
        'shift',
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

    public function kendalaGrajiTripleks()
    {
        return $this->hasMany(KendalaGrajiTriplek::class, 'produksi_graji_triplek_id');
    }

    public function serahTerimaHp()
    {
        // Base relation asal-asalan (akan di-override sepenuhnya di modifyQueryUsing
        // relation manager, sama seperti pola tipe graji/sanding)
        return $this->hasMany(SerahTerimaHp::class, 'id_triplek_hasil_hp', 'id');
    }
}
