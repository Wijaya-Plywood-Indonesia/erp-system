<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VeneerBasahMutasi extends Model
{
    protected $table = 'veneer_basah_mutasis';

    protected $fillable = [
        'tanggal',
        'tujuan',
        'keterangan',
        'dibuat_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function details(): HasMany
    {
        return $this->hasMany(VeneerBasahMutasiDetail::class, 'id_veneer_basah_mutasi');
    }
}
