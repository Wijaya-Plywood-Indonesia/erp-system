<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiSandingJoint extends Model
{
    protected $table = 'validasi_sanding_joint';

    protected $fillable = [
        'id_produksi_sanding_joint',
        'role',
        'status',
    ];
    public function produksiSandingJoint()
    {
        return $this->belongsTo(ProduksiSandingJoint::class, 'id_produksi_sanding_joint');
    }
}
