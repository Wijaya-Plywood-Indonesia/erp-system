<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailReturPenjualan extends Model
{
    protected $table = 'detail_retur_penjualans';

    protected $fillable = [
        'retur_penjualan_id',
        'detail_penjualan_id',
        'nama_barang',
        'custom_nama',
        'satuan',
        'qty',
        'custom_m3',
        'hit_kbk',
        'harga',
        'potongan',
        'subtotal',
        'keterangan',
    ];

    protected $casts = [
        'qty' => 'float',
        'custom_m3' => 'float',
        'harga' => 'decimal:2',
        'potongan' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function returPenjualan(): BelongsTo
    {
        return $this->belongsTo(ReturPenjualan::class, 'retur_penjualan_id');
    }

    public function detailPenjualan(): BelongsTo
    {
        return $this->belongsTo(DetailPenjualan::class, 'detail_penjualan_id');
    }
}
