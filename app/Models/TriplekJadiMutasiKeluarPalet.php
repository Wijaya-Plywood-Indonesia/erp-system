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
        'jumlah_dikembalikan',
        'diterima_by',
        'diterima_at',
    ];

    protected $casts = [
        'nomor_palet'   => 'integer',
        'jumlah_lembar' => 'integer',
        'diterima_at'   => 'datetime',
    ];

    public function mutasiKeluar()
    {
        return $this->belongsTo(TriplekJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }

    public function pemakaianHotpress()
    {
        return $this->hasMany(BahanHotpress::class, 'id_mutasi_keluar_triplek');
    }

    /**
     * Sisa lembar triplek jadi dari palet ini yang masih ada di hotpress —
     * belum tercatat dipakai oleh baris Bahan Hot Press manapun, DAN belum
     * dikembalikan ke gudang. Rumus sama persis dengan
     * VeneerJadiMutasiKeluarPalet::getSisaAttribute() — lihat penjelasan
     * lengkap di sana.
     */
    public function getSisaAttribute(): float
    {
        $terpakai = $this->pemakaianHotpress()->sum('isi');

        return (float) $this->jumlah_lembar - (float) $terpakai - (float) ($this->jumlah_dikembalikan ?? 0);
    }
}