<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokLogCore extends Model
{
    protected $fillable = ['id_jenis_kayu', 'panjang', 'stok_qty', 'harga_satuan', 'nilai_stok', 'id_last_log'];
    protected $casts = ['panjang' => 'float', 'stok_qty' => 'float', 'harga_satuan' => 'float', 'nilai_stok' => 'float'];

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function lastLog()
    {
        return $this->belongsTo(LogLogCore::class, 'id_last_log');
    }
}
