<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiHp extends Model
{
    protected $table = 'validasi_hp';

    protected $fillable = [
        'id_produksi_hp',
        'role',
        'status',
    ];

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }
}
