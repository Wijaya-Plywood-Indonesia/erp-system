<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Builder;

/**
 * Class StokKayuPecahRotary
 *
 * Model penampung akumulasi stok aktif kayu pecah hasil proses rotary
 * yang dikelompokkan berdasarkan jenis kayu dan panjangnya.
 */
class StokKayuPecahRotary extends Model
{
    use HasFactory;

    /**
     * Nama tabel di database.
     *
     * @var string
     */
    protected $table = 'stok_kayu_pecah_rotaries';

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
        'stok_batang'   => 'integer',
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
     * Scope untuk memfilter stok yang lebih dari 0.
     */
    public function scopeAdaStok(Builder $query): Builder
    {
        return $query->where('stok_batang', '>', 0);
    }

    /**
     * Scope untuk memfilter berdasarkan Jenis Kayu dan Panjang.
     */
    public function scopeFilterSpesifikasi(Builder $query, int $idJenisKayu, int $panjang): Builder
    {
        return $query->where('id_jenis_kayu', $idJenisKayu)
            ->where('panjang', $panjang);
    }
}
