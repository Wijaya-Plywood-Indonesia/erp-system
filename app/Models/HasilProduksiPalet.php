<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilProduksiPalet extends Model
{
    protected $fillable = [
        'id_produksi_palet',
        'id_pegawai_palet',
        'id_stok_log_core',
        'modal',
        'hasil',
    ];

    public function produksiPalet(): BelongsTo
    {
        return $this->belongsTo(ProduksiPalet::class, 'id_produksi_palet');
    }

    public function pegawaiPalet(): BelongsTo
    {
        return $this->belongsTo(PegawaiPalet::class, 'id_pegawai_palet');
    }

    public function stokLogCore(): BelongsTo
    {
        return $this->belongsTo(StokLogCore::class, 'id_stok_log_core');
    }
}
