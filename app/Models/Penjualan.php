<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Penjualan extends Model
{
    protected $table = 'penjualans';

    protected $fillable = [
        'tanggal',
        'no_nota',
        'tujuan_nota',
        'alamat',
        'metode_pembayaran',
        'id_rekening_perusahaan',
        'sub_total',
        'ppn_persen',
        'ppn_nominal',
        'total',
        'kendaraan',
        'plat_kendaraan',
        'nama_sopir',
        'status_sync_akuntansi',
        'sync_response',
        'dibuat_oleh',
        'divalidasi_oleh',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'id_rekening_perusahaan' => 'integer',
        'sub_total' => 'decimal:2',
        'ppn_persen' => 'decimal:2',
        'ppn_nominal' => 'decimal:2',
        'total' => 'decimal:2',
        'dibuat_oleh' => 'integer',
        'divalidasi_oleh' => 'integer',
    ];

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }

    public function detail(): HasMany
    {
        return $this->hasMany(DetailPenjualan::class, 'penjualan_id');
    }

    public function rekeningPerusahaan(): BelongsTo
    {
        return $this->belongsTo(RekeningPerusahaan::class, 'id_rekening_perusahaan');
    }
}
