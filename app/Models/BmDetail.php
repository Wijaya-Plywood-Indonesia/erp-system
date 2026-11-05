<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BmDetail extends Model
{
    protected $table = 'bm_detail';
    protected $fillable = ['id_bm', 'nama_barang', 'jumlah', 'satuan', 'keterangan'];
    protected $casts = ['jumlah' => 'float'];

    public function bm(): BelongsTo { return $this->belongsTo(Bm::class, 'id_bm'); }
}
