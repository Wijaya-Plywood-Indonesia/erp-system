<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduksiPalet extends Model
{
    protected $fillable = [
        'tanggal',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function pegawaiPalets(): HasMany
    {
        return $this->hasMany(PegawaiPalet::class, 'id_produksi_palet');
    }

    public function hasilProduksiPalets(): HasMany
    {
        return $this->hasMany(HasilProduksiPalet::class, 'id_produksi_palet');
    }

    public function validasiProduksiPalets(): HasMany
    {
        return $this->hasMany(ValidasiProduksiPalet::class, 'id_produksi_palet');
    }
}
