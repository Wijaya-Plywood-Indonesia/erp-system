<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValidasiRepair extends Model
{
    protected $table = 'validasi_repairs';

    protected $fillable = [
        'id_produksi_repair',
        'role',    // QC, Supervisor, Kepala Produksi, Owner, dll
        'status',  // approved, rejected, rework, pending
    ];

    protected $with = [
        'produksiRepair',
    ];

    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }
}