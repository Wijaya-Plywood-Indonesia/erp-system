<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanProduksi extends Model
{
    protected $table = 'bahan_produksi';

    protected $fillable = [
        'id_produksi_joint',
        'nama_bahan',
        'jumlah',
    ];

    public function produksiJoint()
    {
        return $this->belongsTo(ProduksiJoint::class, 'id_produksi_joint');
    }
}
