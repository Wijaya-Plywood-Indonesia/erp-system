<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JenisBarang extends Model
{
    protected $table = 'jenis_barang';
    protected $fillable = [
        'kode_jenis_barang',
        'nama_jenis_barang',
    ];

    public function barangSetengahJadiHp()
    {
        return $this->hasMany(BarangSetengahJadiHp::class, 'id_jenis_barang');
    }
}
