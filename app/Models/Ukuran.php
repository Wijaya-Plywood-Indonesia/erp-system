<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ukuran extends Model
{
    //
    protected $fillable = [
        'panjang',
        'lebar',
        'tebal',

    ];

    protected $casts = [
        'panjang' => 'float',
        'lebar' => 'float',
        'tebal' => 'float',
    ];

    public function detailMasuks()
    {
        return $this->hasMany(DetailMasuk::class, 'id_ukuran');
    }

    public function detailHasils()
    {
        return $this->hasMany(DetailHasil::class, 'id_ukuran');
    }

    public function detailMasukStik()
    {
        return $this->hasMany(DetailMasukStik::class, 'id_ukuran');
    }

    public function detailMasukKedi()
    {
        return $this->hasMany(DetailMasukKedi::class, 'id_ukuran');
    }

    public function detailBongkarKedi()
    {
        return $this->hasMany(DetailBongkarkedi::class, 'id_ukuran');
    }

    public function detailHasilStik()
    {
        return $this->hasMany(DetailHasilStik::class, 'id_ukuran');
    }

    public function paltformBahanHp()
    {
        return $this->hasMany(PlatformBahanHp::class, 'id_ukuran');
    }

    public function veneerBahanHp()
    {
        return $this->hasMany(VeneerBahanHp::class, 'id_ukuran');
    }

    public function barangSetengahJadiHp()
    {
        return $this->hasMany(BarangSetengahJadiHp::class, 'id_ukuran');
    }

    public function targets()
    {
        return $this->hasMany(DetailHasilPaletRotary::class, 'id_ukuran', 'id');
    }

    public function rencanaPegawai()
    {
        return $this->hasMany(RencanaPegawai::class, 'id_ukuran');
    }
    public function getDimensiAttribute(): string
    {
        return "{$this->panjang} x {$this->lebar} x {$this->tebal}";
    }

    public function getNamaUkuranAttribute(): string
    {
        return "{$this->panjang}mm x {$this->lebar}mm x {$this->tebal}mm";
    }


    protected $appends = ['kubikasi', 'nama_ukuran'];
    public function getKubikasiAttribute()
    {
        $panjang = (float) $this->panjang;
        $lebar = (float) $this->lebar;
        $tebal = (float) $this->tebal;

        return $panjang * $lebar * $tebal;
    }
}
