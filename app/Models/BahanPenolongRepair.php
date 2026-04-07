<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPenolongRepair extends Model
{
    protected $table = 'bahan_penolong_repair';

    protected $fillable = [
        'id_produksi',
        'bahan_penolong_id',
        'jumlah',
    ];

    public function produksiRepair()
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    public function bahanPenolong()
    {
        return $this->belongsTo(BahanPenolongProduksi::class, 'bahan_penolong_id');
    }
}
