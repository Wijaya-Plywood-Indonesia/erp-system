<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BkDetail extends Model
{
    protected $table = 'bk_detail';
    protected $fillable = ['id_bk', 'nama_barang', 'jumlah', 'satuan', 'keterangan', 'ref_type', 'ref_id',
        'harga', 'potongan', 'custom_nama', 'custom_m3'];
    protected $casts = ['jumlah' => 'float'];

    protected static function booted(): void
    {
        // Hapus baris sumbernya (veneer/plywood/platform) juga bila baris nota dihapus (sebelum validasi).
        static::deleting(function (self $d) {
            if (! $d->ref_id) {
                return;
            }
            $sumber = [
                'veneer' => \App\Models\VeneerMutasiDetail::class,
                'plywood' => \App\Models\PlywoodMutasiDetail::class,
                'platform_jadi' => \App\Models\PlatformJadiMutasiDetail::class,
                'platform_mth' => \App\Models\PlatformMthMutasiDetail::class,
            ][$d->ref_type] ?? null;
            if ($sumber) {
                $sumber::whereKey($d->ref_id)->delete();
            }
        });
    }

    public function bk(): BelongsTo { return $this->belongsTo(Bk::class, 'id_bk'); }
    /** Nama lain untuk relasi di atas; dipakai kode cetak yang disalin dari menu lama. */
    public function nota(): BelongsTo { return $this->bk(); }

    public function getQtyAttribute(): float { return (float) ($this->jumlah ?? 0); }
    public function getHargaAttribute(): float { return (float) ($this->attributes['harga'] ?? 0); }
}
