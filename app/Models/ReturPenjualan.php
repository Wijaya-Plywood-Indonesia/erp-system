<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturPenjualan extends Model
{
    protected $table = 'retur_penjualans';

    protected $fillable = [
        'tanggal',
        'no_retur',
        'penjualan_id',
        'no_nota',
        'tujuan_nota',
        'alamat',
        'metode_pembayaran',
        'akun_pengembalian',
        'sub_total',
        'ppn_nominal',
        'total',
        'kendaraan',
        'plat_kendaraan',
        'nama_sopir',
        'keterangan',
        'status_retur',
        'status_sync_akuntansi',
        'sync_response',
        'dibuat_oleh',
        'divalidasi_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'sub_total' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'total' => 'decimal:2',
        'dibuat_oleh' => 'integer',
        'divalidasi_oleh' => 'integer',
    ];

    public function penjualan(): BelongsTo
    {
        return $this->belongsTo(Penjualan::class, 'penjualan_id');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailReturPenjualan::class, 'retur_penjualan_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(DetailReturPenjualan::class, 'retur_penjualan_id');
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }
}
