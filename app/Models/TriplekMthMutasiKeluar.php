<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class TriplekMthMutasiKeluar extends Model
{
    protected $table = 'triplek_mth_mutasi_keluars';

    protected $fillable = [
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'stok_lembar',
        'stok_kubikasi',
        'tujuan',
        'dikeluarkan_by',
        'keterangan',
    ];

    protected $casts = [
        'panjang' => 'float',
        'lebar' => 'float',
        'tebal' => 'float',
        'stok_kubikasi' => 'float',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikeluarkan_by');
    }

    /**
     * Baris serah terima di Produksi Graji Triplek yang menandai mutasi ini
     * sebagai "menunggu diterima" / "sudah diterima".
     * Stok fisik baru dipotong nanti oleh proses produksi Graji Triplek,
     * bukan di sini.
     */
    public function serahTerimaHp(): HasOne
    {
        return $this->hasOne(SerahTerimaHp::class, 'id_triplek_mth_mutasi_keluar');
    }
}
