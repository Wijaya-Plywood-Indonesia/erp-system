<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiPotJelek extends Model
{
    protected $table = 'validasi_pot_jelek';

    protected $fillable = [
        'id_produksi_pot_jelek',
        'role',
        'status',
    ];
    public function produksiPotJelek()
    {
        return $this->belongsTo(ProduksiPotJelek::class, 'id_produksi_pot_jelek');
    }
}
