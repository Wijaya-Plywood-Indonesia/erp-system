<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StokBarangUmum extends Model
{
    protected $table = 'stok_barang_umum';

    protected $fillable = [
        'id_barang_umum',
        'stok_qty',
        'harga_satuan',
        'nilai_stok',
        'id_last_log',
    ];

    protected $casts = [
        'stok_qty'     => 'float',
        'harga_satuan' => 'float',
        'nilai_stok'   => 'float',
    ];

    public function barangUmum(): BelongsTo
    {
        return $this->belongsTo(BarangUmum::class, 'id_barang_umum');
    }

    public function lastLog(): BelongsTo
    {
        return $this->belongsTo(LogBarangUmum::class, 'id_last_log');
    }
}