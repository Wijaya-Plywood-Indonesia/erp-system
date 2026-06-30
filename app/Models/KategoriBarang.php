<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriBarang extends Model
{
    protected $table = 'kategori_barang';
    protected $fillable = [
        'nama_kategori',
    ];

    public function grades()
    {
        return $this->hasMany(Grade::class, 'id_kategori_barang');
    }

    public function criteria()
    {
        return $this->hasMany(Criteria::class, 'id_kategori_barang');
    }
}
