<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidasiProduksiPalet extends Model
{
    protected $fillable = [
        'id_produksi_palet',
        'status',
        'role',
    ];

    public function produksiPalet(): BelongsTo
    {
        return $this->belongsTo(ProduksiPalet::class, 'id_produksi_palet');
    }
}
