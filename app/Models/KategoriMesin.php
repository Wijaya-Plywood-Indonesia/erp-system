<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KategoriMesin extends Model
{
    protected $fillable = [
        'kode_kategori',
        'nama_kategori_mesin',
    ];
    //Relasi ke tabel Mesin.
    public function mesins()
    {
        return $this->hasMany(Mesin::class, 'kategori_mesin_id');
    }
}
