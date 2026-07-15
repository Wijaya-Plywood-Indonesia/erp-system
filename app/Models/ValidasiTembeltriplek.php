<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiTembeltriplek extends Model
{
    protected $table = 'validasi_tembel_triplek';

    protected $fillable = [
        'id_produksi_tembel_triplek',
        'role',
        'status',
    ];

    public function produksiTembeltriplek()
    {
        return $this->belongsTo(ProduksiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }
}
