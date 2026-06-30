<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiJoint extends Model
{
    protected $table = 'validasi_joint';

    protected $fillable = [
        'id_produksi_joint',
        'role',
        'status',
    ];

    public function produksiJoint()
    {
        return $this->belongsTo(ProduksiJoint::class, 'id_produksi_joint');
    }
}
