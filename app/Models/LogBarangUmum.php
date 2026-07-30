<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class LogBarangUmum extends Model
{
    protected $table = 'log_barang_umum';

    protected $fillable = [
        'id_barang_umum',
        'tanggal',
        'tipe_transaksi',
        'keterangan',
        'referensi_type',
        'referensi_id',
        'qty',
        'harga_satuan',
        'nilai',
        'stok_qty_before',
        'nilai_stok_before',
        'stok_qty_after',
        'nilai_stok_after',
    ];

    protected $casts = [
        'tanggal'           => 'date',
        'qty'               => 'float',
        'harga_satuan'      => 'float',
        'nilai'             => 'float',
        'stok_qty_before'   => 'float',
        'nilai_stok_before' => 'float',
        'stok_qty_after'    => 'float',
        'nilai_stok_after'  => 'float',
    ];

    public function barangUmum(): BelongsTo
    {
        return $this->belongsTo(BarangUmum::class, 'id_barang_umum');
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }
}