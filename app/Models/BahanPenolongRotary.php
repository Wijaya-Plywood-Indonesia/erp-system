<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPenolongRotary extends Model
{
    protected $table = 'bahan_penolong_rotary';

    protected $fillable = [
        'id_produksi',
        'nama_bahan',
        'jumlah',
    ];

    public function produksiRotary()
    {
        return $this->belongsTo(ProduksiRotary::class, 'id_produksi');
    }
}
