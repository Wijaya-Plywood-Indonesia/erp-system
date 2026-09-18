<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaVeneerBasah extends Model
{
    protected $table = 'serah_terima_veneer_basah';

    protected $fillable = [
        'id_veneer_basah_mutasi_detail',
        'tujuan',
        'id_produksi_dryer',
        'id_produksi_kedi',
        'diserahkan_oleh',
        'diterima_oleh',
        'ditolak_oleh',
        'alasan_tolak',
        'ditolak_at',
        'status',
    ];

    protected $casts = [
        'ditolak_at' => 'datetime',
    ];

    public function detail(): BelongsTo
    {
        return $this->belongsTo(VeneerBasahMutasiDetail::class, 'id_veneer_basah_mutasi_detail');
    }

    public function produksiDryer(): BelongsTo
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    public function produksiKedi(): BelongsTo
    {
        return $this->belongsTo(ProduksiKedi::class, 'id_produksi_kedi');
    }

    public function isMenunggu(): bool
    {
        return $this->status === 'Menunggu';
    }

    public function isDiterima(): bool
    {
        return $this->status === 'Diterima';
    }

    public function isDitolak(): bool
    {
        return $this->status === 'Ditolak';
    }
}
