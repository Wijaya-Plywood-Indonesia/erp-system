<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiKedi extends Model
{
    protected $table = 'validasi_kedi';

    protected $fillable = [
        'id_produksi_kedi',
        'tipe',
        'role',
        'status',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiKedi::class, 'id_produksi_kedi');
    }
}
