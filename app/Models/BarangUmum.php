<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BarangUmum extends Model
{
    protected $table = 'barang_umum';

    protected $fillable = [
        'nama_barang',
        'satuan',
        'kategori',
        'keterangan',
    ];

    public function stok(): HasOne
    {
        return $this->hasOne(StokBarangUmum::class, 'id_barang_umum');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(LogBarangUmum::class, 'id_barang_umum');
    }
}