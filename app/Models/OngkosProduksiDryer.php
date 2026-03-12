<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OngkosProduksiDryer extends Model
{
    protected $table = 'ongkos_produksi_dryers';

    protected $fillable = [
        'id_produksi_dryer',
        'total_m3',
        'ttl_pekerja',
        'jumlah_mesin',
        'tarif_per_pekerja',
        'tarif_per_mesin',
        'ongkos_pekerja',
        'ongkos_mesin',
        'total_ongkos',
        'ongkos_per_m3',
        'is_final',
    ];

    protected $casts = [
        'is_final' => 'boolean',
        'total_m3' => 'decimal:6',
        'tarif_per_pekerja' => 'decimal:2',
        'tarif_per_mesin' => 'decimal:2',
        'ongkos_pekerja' => 'decimal:2',
        'ongkos_mesin' => 'decimal:2',
        'total_ongkos' => 'decimal:2',
        'ongkos_per_m3' => 'decimal:4',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function produksi()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    // ─── Accessor ────────────────────────────────────────────────────────────

    public function getLabelAttribute(): string
    {
        $p = $this->produksi;
        return $p
            ? $p->tanggal_produksi->format('d/m/Y') . ' | ' . $p->shift
            : "Produksi #{$this->id_produksi_dryer}";
    }

    // ─── Helper ──────────────────────────────────────────────────────────────

    /**
     * Hitung ulang semua field kalkulasi dari data yang sudah ada.
     * Dipanggil dari tombol "Hitung Ulang" di Filament.
     */
    public function recalculate(): void
    {
        if ($this->is_final) {
            return;
        }

        $pekerja = $this->ttl_pekerja * $this->tarif_per_pekerja;
        $mesin = $this->jumlah_mesin * $this->tarif_per_mesin;
        $total = $pekerja + $mesin;

        $this->ongkos_pekerja = $pekerja;
        $this->ongkos_mesin = $mesin;
        $this->total_ongkos = $total;
        $this->ongkos_per_m3 = $this->total_m3 > 0 ? $total / $this->total_m3 : 0;

        $this->save();
    }

    public function isEditable(): bool
    {
        return !$this->is_final;
    }
}