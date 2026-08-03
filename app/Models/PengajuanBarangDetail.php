<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PengajuanBarangDetail extends Model
{
    protected $table = 'pengajuan_barang_detail';

    protected $fillable = [
        'id_pengajuan_barang',
        'id_barang_umum',
        'jumlah',
    ];

    protected $casts = [
        'jumlah' => 'float',
    ];

    public function pengajuan(): BelongsTo
    {
        return $this->belongsTo(PengajuanBarang::class, 'id_pengajuan_barang');
    }

    public function barangUmum(): BelongsTo
    {
        return $this->belongsTo(BarangUmum::class, 'id_barang_umum');
    }
}