<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidasiDempul extends Model
{
    protected $table = 'validasi_dempuls';

    protected $fillable = [
        'id_produksi_dempul',
        'role',
        'status',
    ];

    public function produksiDempul()
    {
        return $this->belongsTo(ProduksiDempul::class, 'id_produksi_dempul');
    }
}
