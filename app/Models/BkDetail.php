<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkDetail extends Model
{
    protected $table = 'bk_detail';
    protected $fillable = ['id_bk', 'nama_barang', 'jumlah', 'satuan', 'keterangan'];
    protected $casts = ['jumlah' => 'float'];

    public function bk(): BelongsTo { return $this->belongsTo(Bk::class, 'id_bk'); }
}
