<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TriplekJadiMutasiKeluarPalet extends Model
{
    protected $table = 'triplek_jadi_mutasi_keluar_palets';

    protected $fillable = [
        'id_mutasi_keluar',
        'nomor_palet',
        'jumlah_lembar',
        'diterima_by',
        'diterima_at',
        'ditolak_by',
        'alasan_tolak',
        'ditolak_at',
    ];

    protected $casts = [
        'nomor_palet'   => 'integer',
        'jumlah_lembar' => 'integer',
        'diterima_at'   => 'datetime',
        'ditolak_at'    => 'datetime',
    ];

    public function mutasiKeluar()
    {
        return $this->belongsTo(TriplekJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }

    public function pemakaianHotpress()
    {
        return $this->hasMany(BahanHotpress::class, 'id_mutasi_keluar_triplek');
    }

    public function getSisaAttribute(): float
    {
        $terpakai = $this->pemakaianHotpress()->sum('isi');
        return (float) $this->jumlah_lembar - (float) $terpakai;
    }
}