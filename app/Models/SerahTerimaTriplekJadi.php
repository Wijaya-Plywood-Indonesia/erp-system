<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaTriplekJadi extends Model
{
    protected $table = 'serah_terima_triplek_jadi';

    protected $fillable = [
        'id_hasil_graji_triplek',
        'id_hasil_sanding',
        'id_produksi_pilih_plywood',
        'diserahkan_oleh',
        'diterima_oleh',
        'status',
    ];

    // ─────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────

    /**
     * Hasil produksi Graji Triplek, dipakai saat serah dari Graji -> Pilih Plywood.
     */
    public function hasilGrajiTriplek(): BelongsTo
    {
        return $this->belongsTo(HasilGrajiTriplek::class, 'id_hasil_graji_triplek');
    }

    /**
     * Hasil produksi Sanding, dipakai saat serah dari Sanding -> Pilih Plywood.
     */
    public function hasilSanding(): BelongsTo
    {
        return $this->belongsTo(HasilSanding::class, 'id_hasil_sanding');
    }

    /**
     * Produksi Pilih Plywood tujuan penyerahan barang ini.
     */
    public function produksiPilihPlywood(): BelongsTo
    {
        return $this->belongsTo(ProduksiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Tipe sumber barang:
     * - 'graji_triplek' -> berasal dari Graji Triplek
     * - 'sanding' -> berasal dari Sanding
     */
    public function getTipeSumberAttribute(): string
    {
        if ($this->id_hasil_graji_triplek) {
            return 'graji_triplek';
        }

        return 'sanding';
    }

    /**
     * Label asal barang yang sebenarnya, untuk ditampilkan di UI.
     */
    public function getAsalLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->id_hasil_graji_triplek => 'Graji Triplek',
            (bool) $this->id_hasil_sanding => 'Sanding',
            default => '-',
        };
    }

    /**
     * Ambil record hasil produksi apapun sumbernya
     * (hasil Graji Triplek atau hasil Sanding).
     */
    public function getHasilAttribute()
    {
        return $this->hasilGrajiTriplek
            ?? $this->hasilSanding;
    }

    /**
     * Barang setengah jadi terkait, terlepas dari nama relasi yang beda-beda
     * antar model hasil (HasilSanding pakai `barangSetengahJadi`,
     * HasilGrajiTriplek pakai `barangSetengahJadiHp`).
     */
    public function getBarangSetengahJadiAttribute()
    {
        $hasil = $this->hasil;

        return $hasil?->barangSetengahJadi ?? $hasil?->barangSetengahJadiHp ?? null;
    }

    /**
     * Jumlah/isi barang, terlepas dari nama kolom yang beda-beda antar model
     * hasil (HasilGrajiTriplek pakai `isi`, HasilSanding pakai `kuantitas`).
     */
    public function getJumlahAttribute()
    {
        return $this->hasil->isi ?? $this->hasil->kuantitas ?? null;
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
        return (float) ($this->jumlah ?? 0);
    }

    /**
     * Sisa = qty asli dikurangi total pemakaian di Pilih Plywood.
     *
     * NOTE: asumsi ada model `BahanPilihPlywood` dengan kolom
     * `id_serah_terima_triplek_jadi` dan `kuantitas` sebagai pencatat
     * pemakaian. Sesuaikan nama model/kolom bila berbeda di skema Anda.
     */
    public function getSisaAttribute(): float
    {
        // Pastikan Model BahanPilihPlywood sudah ter-import di atas jika belum
        $terpakai = \App\Models\BahanPilihPlywood::where('id_serah_terima_triplek_jadi', $this->id)
            ->sum('jumlah'); // Ubah menjadi jumlah

        return $this->qtyAsli - (float) $terpakai;
    }
}
