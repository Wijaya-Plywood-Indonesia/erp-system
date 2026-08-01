<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LogLogCore extends Model
{
    protected $fillable = [
        'id_jenis_kayu',
        'panjang',
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
        'tanggal' => 'date',
        'panjang' => 'float',
        'qty' => 'float',
        'harga_satuan' => 'float',
        'nilai' => 'float',
        'stok_qty_before' => 'float',
        'nilai_stok_before' => 'float',
        'stok_qty_after' => 'float',
        'nilai_stok_after' => 'float',
    ];

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function referensi()
    {
        return $this->morphTo();
    }
}
