<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiGrajiBalken extends Model
{
    protected $table = 'validasi_graji_balken';

    protected $fillable = [
        'id_produksi_graji_balken',
        'role',
        'status',
    ];

    public function produksiGrajiBalken()
    {
        return $this->belongsTo(ProduksiGrajiBalken::class, 'id_produksi_graji_balken');
    }
}
