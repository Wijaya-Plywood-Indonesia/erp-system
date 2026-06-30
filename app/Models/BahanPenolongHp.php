<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPenolongHp extends Model
{
    protected $table = 'bahan_penolong_hp';

    protected $fillable = [
        'id_produksi_hp',
        'nama_bahan',
        'jumlah',
    ];

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }
}
