<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiTerimaGudangSatu extends Model
{
    protected $table = 'validasi_terima_gudang_satu';

    protected $fillable = [
        'id_produksi_terima_gudang_satu',
        'role',
        'status',
    ];

    public function produksiTerimaGudangSatu()
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }
}
