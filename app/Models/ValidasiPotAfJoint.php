<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiPotAfJoint extends Model
{
    protected $table = 'validasi_pot_af_joint';

    protected $fillable = [
        'id_produksi_pot_af_joint',
        'role',
        'status',
    ];
    public function produksiPotAfJoint()
    {
        return $this->belongsTo(ProduksiPotAfJoint::class, 'id_produksi_pot_af_joint');
    }
}
