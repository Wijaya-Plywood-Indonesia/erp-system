<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerJadiMutasiKeluar extends Model
{
    protected $fillable = [
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'jumlah_palet',
        'stok_lembar',
        'stok_kubikasi',
        'tujuan',
        'dikeluarkan_by',
        'keterangan',
        'diterima_by',
        'diterima_at',
        'id_produksi_hp',
    ];

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'dikeluarkan_by');
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }

    public function palets()
    {
        return $this->hasMany(VeneerJadiMutasiKeluarPalet::class, 'id_mutasi_keluar');
    }
}
