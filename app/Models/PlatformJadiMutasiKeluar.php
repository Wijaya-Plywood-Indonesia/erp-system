<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformJadiMutasiKeluar extends Model
{
    protected $table = 'platform_jadi_mutasi_keluars';

    protected $fillable = [
        'id_jenis_barang',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'jumlah_palet',
        'stok_lembar',
        'stok_kubikasi',
        'tujuan',
        'dikeluarkan_by',
        'diterima_by',
        'diterima_at',
        'id_produksi_hp',
        'keterangan',
    ];

    protected $casts = [
        'panjang' => 'float',
        'lebar' => 'float',
        'tebal' => 'float',
        'stok_kubikasi' => 'float',
        'diterima_at' => 'datetime',
    ];

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'id_jenis_barang');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'dikeluarkan_by');
    }

    public function penerima()
    {
        return $this->belongsTo(User::class, 'diterima_by');
    }

    public function produksiHotPress()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }

    public function palets()
    {
        return $this->hasMany(PlatformJadiMutasiKeluarPalet::class, 'id_mutasi_keluar')
            ->orderBy('nomor_palet');
    }
}
