<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TriplekJadiMutasiKeluar extends Model
{
    protected $table = 'triplek_jadi_mutasi_keluars';

    public const STATUS_DIKIRIM  = 'dikirim';
    public const STATUS_DITERIMA = 'diterima';

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
        'status',
        'dikonfirmasi_by',
        'dikonfirmasi_at',
    ];

    protected $casts = [
        'panjang'         => 'float',
        'lebar'           => 'float',
        'tebal'           => 'float',
        'jumlah_palet'    => 'integer',
        'stok_lembar'     => 'integer',
        'stok_kubikasi'   => 'float',
        'dikonfirmasi_at' => 'datetime',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dikeluarkan_by');
    }

    public function palets(): HasMany
    {
        return $this->hasMany(TriplekJadiMutasiKeluarPalet::class, 'id_mutasi_keluar');
    }
}
