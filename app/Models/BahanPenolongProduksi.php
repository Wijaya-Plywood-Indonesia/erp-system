<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPenolongProduksi extends Model
{
    protected $table = 'bahan_penolong_produksi';

    protected $fillable = [
        'nama_bahan_penolong',
        'satuan',
        'kategori_produksi',
        'harga',
    ];
}
