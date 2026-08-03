<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanBarang extends Model
{
    protected $table = 'pengajuan_barang';

    protected $fillable = [
        'tanggal',
        'lokasi_penggunaan',
        'keterangan',
        'foto',
        'diajukan_oleh',
        'status_kepala_produksi',
        'disetujui_kepala_oleh',
        'disetujui_kepala_at',
        'status_admin_barang',
        'disetujui_admin_oleh',
        'disetujui_admin_at',
        'diproses_at',
    ];

    protected $casts = [
        'tanggal'             => 'date',
        'disetujui_kepala_at' => 'datetime',
        'disetujui_admin_at'  => 'datetime',
        'diproses_at'         => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(PengajuanBarangDetail::class, 'id_pengajuan_barang');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function kepalaProduksi(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_kepala_oleh');
    }

    public function adminBarang(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disetujui_admin_oleh');
    }

    public function sudahDisetujuiKeduanya(): bool
    {
        return $this->status_kepala_produksi === 'disetujui'
            && $this->status_admin_barang === 'disetujui';
    }

    public function adaYangMenolak(): bool
    {
        return $this->status_kepala_produksi === 'ditolak'
            || $this->status_admin_barang === 'ditolak';
    }

    public function sudahDiproses(): bool
    {
        return $this->diproses_at !== null;
    }

    public function getStatusRingkasAttribute(): string
    {
        if ($this->sudahDiproses()) {
            return 'Selesai - Stok Terpotong';
        }

        if ($this->adaYangMenolak()) {
            return 'Ditolak';
        }

        if ($this->sudahDisetujuiKeduanya()) {
            return 'Menunggu Diproses';
        }

        return 'Menunggu Persetujuan';
    }
}