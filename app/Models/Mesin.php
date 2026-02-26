<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Mesin extends Model
{
    //isi tabel dan variable insert
    protected $fillable = [
        'kategori_mesin_id',
        'nama_mesin',
        'ongkos_mesin',
        'penyusutan',
        'no_akun',
        'detail_mesin',
    ];
    //Relasi dengan tabel Kategori Mesin. 
    public function kategoriMesin()
    {
        return $this->belongsTo(KategoriMesin::class, 'kategori_mesin_id');
    }
    public function mesins()
    {
        return $this->hasMany(Mesin::class, 'id_mesin');
    }

}
