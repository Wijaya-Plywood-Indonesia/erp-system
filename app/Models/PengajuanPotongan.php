<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PengajuanPotongan extends Model
{
    protected $table = 'pengajuan_potongans';

    protected $fillable = [
        'id_pegawai',
        'kode_pegawai',
        'tanggal',
        'divisi',
        'nominal_awal',
        'nominal_usulan',
        'nominal_final',
        'estimasi_gaji',
        'alasan_pengajuan',
        'status',
        'diajukan_oleh',
        'diproses_oleh',
        'diproses_at',
        'alasan_penolakan',
    ];

    protected $casts = [
        'tanggal' => 'date',
        'nominal_awal' => 'decimal:2',
        'nominal_usulan' => 'decimal:2',
        'nominal_final' => 'decimal:2',
        'estimasi_gaji' => 'decimal:2',
        'diproses_at' => 'datetime',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function pengaju(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diajukan_oleh');
    }

    public function pemroses(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(PengajuanPotonganHistory::class, 'pengajuan_potongan_id')->latest('id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }
}
