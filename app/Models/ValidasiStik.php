<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiStik extends Model
{
    protected $table = 'validasi_stik';

    protected $fillable = [
        'id_produksi_stik',
        'role',
        'status',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiStik::class, 'id_produksi_stik');
    }
}
