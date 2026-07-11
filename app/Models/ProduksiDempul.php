<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class ProduksiDempul extends Model
{
    protected $table = 'produksi_dempuls';

    protected $fillable = [
        'tanggal',
        'tanggal_produksi',
        'kendala',
    ];

    public function rencanaPegawaiDempuls()
    {
        return $this->hasMany(RencanaPegawaiDempul::class, 'id_produksi_dempul');
    }

    public function detailDempuls()
    {
        return $this->hasMany(DetailDempul::class, 'id_produksi_dempul');
    }

    public function validasiDempuls()
    {
        return $this->hasMany(ValidasiDempul::class, 'id_produksi_dempul');
    }

    public function bahanDempuls()
    {
        return $this->hasMany(BahanDempul::class, 'id_produksi_dempul');
    }

    // ⬇️ INI YANG BELUM ADA — tambahkan method ini
    public function serahTerimaTriplekCacat()
    {
        return $this->hasMany(SerahTerimaTriplekCacat::class, 'id_produksi_dempul');
    }

    protected function tanggalDempul(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->{static::kolomTanggalAktif()},
        );
    }

    public static function kolomTanggalAktif(): string
    {
        static $kolom = null;

        if ($kolom !== null) {
            return $kolom;
        }

        if (Schema::hasColumn('produksi_dempuls', 'tanggal')) {
            return $kolom = 'tanggal';
        }

        return $kolom = 'tanggal_produksi';
    }
}
