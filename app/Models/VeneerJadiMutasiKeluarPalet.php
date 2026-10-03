<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerJadiMutasiKeluarPalet extends Model
{
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
        return $this->belongsTo(VeneerJadiMutasiKeluar::class, 'id_mutasi_keluar');
    }

    public function pemakaianHotpress()
    {
        return $this->hasMany(BahanHotpress::class, 'id_mutasi_keluar_palet');
    }

    /**
     * Sisa lembar veneer jadi dari palet ini yang masih ada di hotpress —
     * belum tercatat dipakai oleh baris Bahan Hot Press manapun, DAN
     * belum dikembalikan ke gudang.
     *
     * Contoh: palet 100 lembar, 1 baris pemakaian isi=90 (belum pernah
     * dikembalikan) -> sisa = 100 - 90 - 0 = 10. Setelah dikembalikan 5
     * lembar (jumlah_dikembalikan jadi 5) -> sisa = 100 - 90 - 5 = 5.
     *
     * `jumlah_dikembalikan` sengaja disimpan di PALET (bukan di baris
     * bahan_hotpress) karena "sisa yang belum dipakai" adalah properti
     * palet itu sendiri, bukan properti satu baris pemakaian tertentu.
     */
    public function getSisaAttribute(): float
    {
        $terpakai = $this->pemakaianHotpress()->sum('isi');

        return (float) $this->jumlah_lembar - (float) $terpakai - (float) ($this->jumlah_dikembalikan ?? 0);
    }
}
