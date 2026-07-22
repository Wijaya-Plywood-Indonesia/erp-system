<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Baseline "tutup buku" WIP sanding per spesifikasi.
 *
 * Setiap kali pengawas menekan "Selesaikan WIP" di halaman Stok Triplek Jadi,
 * satu baris dibuat di sini menyimpan kumulatif keluar & masuk sanding pada
 * saat itu. Getter WIP mengurangkan baseline ini, sehingga sisa WIP jadi 0
 * dan barang yang tidak kembali dianggap susut tercatat.
 */
class WipSandingReset extends Model
{
    protected $table = 'wip_sanding_resets';

    protected $fillable = [
        'spec_key',
        'id_jenis_kayu',
        'panjang',
        'lebar',
        'tebal',
        'kw_grade',
        'keluar_kumulatif',
        'masuk_kumulatif',
        'direset_oleh',
    ];

    protected $casts = [
        'panjang'          => 'float',
        'lebar'            => 'float',
        'tebal'            => 'float',
        'keluar_kumulatif' => 'float',
        'masuk_kumulatif'  => 'float',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function pereset(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direset_oleh');
    }
}