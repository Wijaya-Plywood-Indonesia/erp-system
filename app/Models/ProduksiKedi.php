<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProduksiKedi extends Model
{
    protected $table = 'produksi_kedi';

    protected $fillable = [
        'tanggal',
        'rencana_bongkar',
        'tanggal_bongkar',
        'tanggal_actual_bongkar',
        'id_mesin',
        'kendala',
        'kode_kedi',
        'status',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'rencana_bongkar' => 'date',
        'tanggal_bongkar' => 'date',
        'tanggal_actual_bongkar' => 'date',
    ];

    public function validasiMasuk()
    {
        return $this->hasOne(ValidasiKedi::class, 'id_produksi_kedi')->where('tipe', 'masuk')->latestOfMany();
    }

    public function validasiBongkar()
    {
        return $this->hasOne(ValidasiKedi::class, 'id_produksi_kedi')->where('tipe', 'bongkar')->latestOfMany();
    }

    public function isMasukDivalidasi()
    {
        return $this->validasiMasuk?->status === 'divalidasi';
    }

    public function isBongkarDivalidasi()
    {
        return $this->validasiBongkar?->status === 'divalidasi';
    }

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

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

    public function kendalaKedis()
    {
        return $this->hasMany(KendalaKedi::class, 'produksi_kedi_id');
    }
}
