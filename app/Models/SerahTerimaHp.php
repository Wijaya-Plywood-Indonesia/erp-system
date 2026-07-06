<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaHp extends Model
{
    protected $table = 'serah_terima_hp';

    protected $fillable = [
        'id_triplek_hasil_hp',
        'id_produksi_graji_triplek',
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

    public function produksiGrajiTriplek(): BelongsTo
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'id_produksi_graji_triplek');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

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
        return (float) ($this->triplekHasilHp->isi ?? 0);
    }

    public function getSisaAttribute(): float
    {
        $terpakai = MasukGrajiTriplek::where('id_serah_terima_hp', $this->id)->sum('isi');

        return $this->qtyAsli - (float) $terpakai;
    }
}
