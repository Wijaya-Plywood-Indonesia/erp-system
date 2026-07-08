<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiHp extends Model
{
    protected $table = 'produksi_hp';

    protected $fillable = [
        'tanggal_produksi',
        'shift',
        'kendala',
    ];

    public function detailPegawaiHp()
    {
        return $this->hasMany(DetailPegawaiHp::class, 'id_produksi_hp');
    }

    public function veneerBahanHp()
    {
        return $this->hasMany(VeneerBahanHp::class, 'id_produksi_hp');
    }

    public function platformBahanHp()
    {
        return $this->hasMany(PlatformBahanHp::class, 'id_produksi_hp');
    }

    public function platformHasilHp()
    {
        return $this->hasMany(PlatformHasilHp::class, 'id_produksi_hp');
    }

    public function triplekHasilHp()
    {
        return $this->hasMany(TriplekHasilHp::class, 'id_produksi_hp');
    }

    public function bahanHotpress()
    {
        return $this->hasMany(BahanHotpress::class, 'id_produksi_hp');
    }

    public function validasiHp()
    {
        return $this->hasMany(ValidasiHp::class, 'id_produksi_hp');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiHp::class, 'id_produksi_hp')->latestOfMany();
    }

    public function bahanPenolongHp()
    {
        return $this->hasMany(BahanPenolongHp::class, 'id_produksi_hp');
    }

    public function rencanaKerjaHp()
    {
        return $this->hasMany(RencanaKerjaHp::class, 'id_produksi_hp');
    }

    public function kendalaHps()
    {
        return $this->hasMany(KendalaHp::class, 'produksi_hp_id');
    }

    public function mutasiMasuk()
    {
        return $this->hasManyThrough(
            VeneerJadiMutasiKeluarPalet::class,
            VeneerJadiMutasiKeluar::class,
            'id_produksi_hp',
            'id_mutasi_keluar',
            'id',
            'id'
        )->orWhereHas('mutasiKeluar', fn($q) => $q->whereNull('diterima_by'));
    }

    public function serahTerimaHp()
    {
        return $this->hasMany(SerahTerimaHp::class, 'id_triplek_hasil_hp', 'id');
    }
}
