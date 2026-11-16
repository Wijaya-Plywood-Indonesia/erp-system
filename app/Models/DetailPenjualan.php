<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailPenjualan extends Model
{
    protected $table = 'detail_penjualans';

    protected $fillable = [
        'penjualan_id',
        'kategori_barang',
        'id_ukuran',
        'id_jenis_kayu',
        'kw_grade',
        'ukuran_key',
        'tipe_veneer',
        'id_barang_umum',
        'nama_barang',
        'custom_nama',
        'jumlah',
        'custom_m3',
        'satuan',
        'harga',
        'potongan',
        'subtotal',
        'keterangan',
    ];

    protected $casts = [
        'jumlah' => 'float',
        'custom_m3' => 'float',
        'harga' => 'decimal:2',
        'potongan' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function barangUmum(): BelongsTo
    {
        return $this->belongsTo(BarangUmum::class, 'id_barang_umum');
    }

    public function getQtyAttribute(): float
    {
        return (float) ($this->jumlah ?? 0);
    }
}
