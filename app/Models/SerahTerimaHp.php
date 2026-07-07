<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaHp extends Model
{
    protected $table = 'serah_terima_hp';

    protected $fillable = [
        'id_triplek_hasil_hp',
        'id_platform_hasil_hp',
        'id_produksi_graji_triplek',
        'id_produksi_sanding',
        'diserahkan_oleh',
        'diterima_oleh',
        'status',
    ];

    // ─────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────

    public function triplekHasilHp(): BelongsTo
    {
        return $this->belongsTo(TriplekHasilHp::class, 'id_triplek_hasil_hp');
    }

    public function platformHasilHp(): BelongsTo
    {
        return $this->belongsTo(PlatformHasilHp::class, 'id_platform_hasil_hp');
    }

    public function produksiGrajiTriplek(): BelongsTo
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'id_produksi_graji_triplek');
    }

    public function produksiSanding(): BelongsTo
    {
        return $this->belongsTo(ProduksiSanding::class, 'id_produksi_sanding');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Tipe sumber hasil: 'triplek' atau 'platform'.
     */
    public function getTipeSumberAttribute(): string
    {
        return $this->id_platform_hasil_hp ? 'platform' : 'triplek';
    }

    /**
     * Ambil record hasil produksi apapun sumbernya (triplek atau platform).
     */
    public function getHasilAttribute()
    {
        return $this->tipeSumber === 'platform'
            ? $this->platformHasilHp
            : $this->triplekHasilHp;
    }

    public function isMenunggu(): bool
    {
        return $this->diterima_oleh === '-';
    }

    public function getLabelStatusAttribute(): string
    {
        return $this->isMenunggu() ? 'Menunggu' : 'Diterima';
    }

    public function getQtyAsliAttribute(): float
    {
        return (float) ($this->hasil->isi ?? 0);
    }

    /**
     * Sisa = qty asli dikurangi total pemakaian.
     * - Sumber triplek: pemakaian dihitung dari MasukGrajiTriplek.
     * - Sumber platform: pemakaian dihitung dari ModalSanding.
     */
    public function getSisaAttribute(): float
    {
        $terpakai = $this->tipeSumber === 'triplek'
            ? MasukGrajiTriplek::where('id_serah_terima_hp', $this->id)->sum('isi')
            : ModalSanding::where('id_serah_terima_hp', $this->id)->sum('kuantitas');

        return $this->qtyAsli - (float) $terpakai;
    }
}
