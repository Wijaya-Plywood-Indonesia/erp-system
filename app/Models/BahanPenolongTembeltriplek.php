<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanPenolongTembeltriplek extends Model
{
    protected $table = 'bahan_penolong_tembel_triplek';

    protected $fillable = [
        'id_produksi_tembel_triplek',
        'nama_bahan',
        'jumlah',
    ];

    public function produksiTembeltriplek()
    {
        return $this->belongsTo(ProduksiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function bahanPenolong()
    {
        return $this->belongsTo(BahanPenolongProduksi::class, 'id_bahan_penolong');
    }
}
