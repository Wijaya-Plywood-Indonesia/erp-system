<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformJadiMutasiKeluarPalet extends Model
{
    protected $table = 'platform_jadi_mutasi_keluar_palets';

    protected $fillable = [
        'id_mutasi_keluar',
        'nomor_palet',
        'jumlah_lembar',
        'jumlah_dikembalikan',
        'diterima_by',
        'diterima_at',
    ];

    public function mutasiKeluar()
    {
        return $this->belongsTo(PlatformJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }

    public function bahanHotpress()
    {
        return $this->hasMany(BahanHotpress::class, 'id_mutasi_keluar_platform');
    }

    /**
     * Sisa lembar platform jadi dari palet ini yang masih ada di hotpress —
     * belum tercatat dipakai oleh baris Bahan Hot Press manapun, DAN belum
     * dikembalikan ke gudang. Rumus sama persis dengan
     * VeneerJadiMutasiKeluarPalet::getSisaAttribute() — lihat penjelasan
     * lengkap di sana.
     */
    public function getSisaAttribute(): float
    {
        $terpakai = $this->bahanHotpress()->sum('isi');

        return (float) $this->jumlah_lembar - (float) $terpakai - (float) ($this->jumlah_dikembalikan ?? 0);
    }
}
