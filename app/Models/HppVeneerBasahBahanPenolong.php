<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HppVeneerBasahBahanPenolong extends Model
{
    protected $table = 'hpp_veneer_basah_bahan_penolong';

    protected $fillable = [
        'id_log',
        'kw',
        'bahan_penolong_id',
        'nama_bahan',
        'satuan',
        'jumlah',
        'harga_satuan',
        'nilai_total',
        'hpp_per_m3',
    ];

    protected $casts = [
        'jumlah'       => 'float',
        'harga_satuan' => 'float',
        'nilai_total'  => 'float',
        'hpp_per_m3'   => 'float',
    ];

    public function log(): BelongsTo
    {
        return $this->belongsTo(HppVeneerBasahLog::class, 'id_log');
    }

    public function masterBahan(): BelongsTo
    {
        return $this->belongsTo(BahanPenolongProduksi::class, 'bahan_penolong_id');
    }
}