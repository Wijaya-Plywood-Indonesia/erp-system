<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiNyusup extends Model
{
    protected $table = 'validasi_nyusup';

    protected $fillable = [
        'id_produksi_nyusup ',
        'role',
        'status',

    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiNyusup::class, 'id_produksi_nyusup');
    }
}
