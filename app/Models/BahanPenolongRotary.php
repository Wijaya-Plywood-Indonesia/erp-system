<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPenolongRotary extends Model
{
    protected $table = 'bahan_penolong_rotary';

    protected $fillable = [
        'id_produksi',
        'bahan_penolong_id',
        'jumlah',
    ];

    public function produksiRotary()
    {
        return $this->belongsTo(ProduksiRotary::class, 'id_produksi');
    }

    public function bahanPenolong()
{
    return $this->belongsTo(BahanPenolongProduksi::class, 'bahan_penolong_id');
}
}
