<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilRepair extends Model
{
    protected $table = 'hasil_repairs';

    protected $fillable = [
        'id_produksi_repair',
        'id_rencana_repair',
        'jumlah',
        'keterangan'
    ];

    protected $attributes = [
        'jumlah' => 0,
    ];

    // ==============================
    // RELASI
    // ==============================

    /** Hari produksi (header) */
    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    public function rencanaRepair(): BelongsTo
    {
        return $this->belongsTo(RencanaRepair::class, 'id_rencana_repair');
    }
}
