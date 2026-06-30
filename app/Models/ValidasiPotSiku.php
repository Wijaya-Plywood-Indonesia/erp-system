<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiPotSiku extends Model
{
    protected $table = 'validasi_pot_siku';

    protected $fillable = [
        'id_produksi_pot_siku',
        'role',
        'status',
    ];
    public function produksiPotSiku()
    {
        return $this->belongsTo(ProduksiPotSiku::class, 'id_produksi_pot_siku');
    }
}
