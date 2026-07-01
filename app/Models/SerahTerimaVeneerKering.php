<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaVeneerKering extends Model
{
    protected $table = 'serah_terima_veneer_kering';

    protected $fillable = [
        'id_detail_hasil',
        'id_detail_bongkar_kedi',
        'tipe_sumber',
        'id_produksi_repair',
        'diserahkan_oleh',
        'diterima_oleh',
        'status',
    ];

    protected $casts = [
        'tipe_sumber' => 'string',
    ];

    // ─────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────

    public function detailHasil(): BelongsTo
    {
        return $this->belongsTo(DetailHasil::class, 'id_detail_hasil');
    }

    public function detailBongkarKedi(): BelongsTo
    {
        return $this->belongsTo(DetailBongkarKedi::class, 'id_detail_bongkar_kedi');
    }

    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function isMenunggu(): bool
    {
        return $this->diterima_oleh === '-';
    }

    /**
     * Ambil model sumber (DetailHasil atau DetailBongkarKedi)
     * tanpa perlu tahu tipe_sumber di luar model.
     */
    public function getSumberAttribute(): DetailHasil|DetailBongkarKedi|null
    {
        return match ($this->tipe_sumber) {
            'dryer' => $this->detailHasil,
            'kedi' => $this->detailBongkarKedi,
            default => null,
        };
    }

    /**
     * Label ringkas untuk ditampilkan di tabel
     */
    public function getLabelSumberAttribute(): string
    {
        return match ($this->tipe_sumber) {
            'dryer' => 'Press Dryer',
            'kedi' => 'Kedi',
            default => '-',
        };
    }
}
