<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PegawaiPalet extends Model
{
    protected $fillable = [
        'id_produksi_palet',
        'id_pegawai',
        'jam_masuk',
        'jam_pulang',
        'izin',
        'keterangan',
    ];

    public function produksiPalet(): BelongsTo
    {
        return $this->belongsTo(ProduksiPalet::class, 'id_produksi_palet');
    }

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function hasilProduksiPalets(): HasMany
    {
        return $this->hasMany(HasilProduksiPalet::class, 'id_pegawai_palet');
    }
}
