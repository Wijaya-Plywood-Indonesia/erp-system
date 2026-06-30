<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiGrajiTriplek extends Model
{
    protected $table = 'validasi_graji_triplek';

    protected $fillable = [
        'id_produksi_graji_triplek',
        'role',
        'status',
    ];

    public function produksiGrajiTriplek()
    {
        return $this->belongsTo(ProduksiGrajiTriplek::class, 'id_produksi_graji_triplek');
    }
}
