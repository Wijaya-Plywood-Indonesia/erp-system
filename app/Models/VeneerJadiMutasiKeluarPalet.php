<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerJadiMutasiKeluarPalet extends Model
{
    protected $fillable = [
        'id_mutasi_keluar',
        'nomor_palet',
        'jumlah_lembar',
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


    public function getSisaAttribute(): float
    {
        $terpakai = $this->pemakaianHotpress()->sum('isi');
        return (float) $this->jumlah_lembar - (float) $terpakai;
    }
}
