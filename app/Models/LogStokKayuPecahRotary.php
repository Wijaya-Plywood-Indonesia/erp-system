<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class LogStokKayuPecahRotary
 *
 * Model catatan riwayat (audit trail) transaksi kayu pecah
 * baik transaksi masuk (dari Lahan Rotary) maupun keluar (dipakai Graji Balken).
 */
class LogStokKayuPecahRotary extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database.
     *
     * @var string
     */
    protected $table = 'log_stok_kayu_pecah_rotaries';

    /**
     * Field yang dilindungi dari mass assignment.
     *
     * @var array
     */
    protected $guarded = ['id'];

    /**
     * Cast tipe data atribut.
     *
     * @var array
     */
    protected $casts = [
        'id_jenis_kayu' => 'integer',
        'panjang'       => 'integer',
        'id_lahan'      => 'integer',
        'jumlah_batang' => 'integer',
        'stok_before'   => 'integer',
        'stok_after'    => 'integer',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /**
     * Relasi ke model JenisKayu (Many-to-One).
     */
    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    /**
     * Relasi ke model Lahan asal (Many-to-One, Optional).
     */
    public function lahan(): BelongsTo
    {
        return $this->belongsTo(Lahan::class, 'id_lahan');
    }

    /**
     * Relasi Polymorphic ke transaksi referensi (misal: PenggunaanLahanRotary atau ProduksiBalken).
     */
    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Scope untuk transaksi tipe MASUK.
     */
    public function scopeMasuk(Builder $query): Builder
    {
        return $query->where('tipe', 'masuk');
    }

    /**
     * Scope untuk transaksi tipe KELUAR.
     */
    public function scopeKeluar(Builder $query): Builder
    {
        return $query->where('tipe', 'keluar');
    }
}
