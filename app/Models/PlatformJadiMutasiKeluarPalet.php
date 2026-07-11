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

    public function getSisaAttribute()
    {
        $terpakai = $this->bahanHotpress()->sum('isi');
        return (float) $this->jumlah_lembar - $terpakai;
    }
}
