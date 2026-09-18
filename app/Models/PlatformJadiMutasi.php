<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformJadiMutasi extends Model
{
    protected $table = 'platform_jadi_mutasis';

    protected $fillable = [
        'tanggal', 'tipe_transaksi', 'no_nota', 'tujuan_nota',
        'status', 'keterangan', 'id_nota_bk', 'id_nota_bm', 'dibuat_oleh',
    ];

    protected $casts = ['tanggal' => 'date'];

    public function details(): HasMany
    {
        return $this->hasMany(PlatformJadiMutasiDetail::class, 'id_platform_jadi_mutasi');
    }

    public function notaBk(): BelongsTo
    {
        return $this->belongsTo(NotaBarangKeluar::class, 'id_nota_bk');
    }

    public function notaBm(): BelongsTo
    {
        return $this->belongsTo(NotaBarangMasuk::class, 'id_nota_bm');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
