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
        'id_detail_dempul',
        'id_hasil_tembel_triplek',
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
     * Hasil perbaikan Dempul, dipakai saat serah dari Dempul -> Pilih Plywood
     * (barang cacat yang sudah didempul dan sekarang jadi bagus).
     */
    public function detailDempul(): BelongsTo
    {
        return $this->belongsTo(DetailDempul::class, 'id_detail_dempul');
    }

    /**
     * Hasil perbaikan Tembel Triplek, dipakai saat serah dari Tembel Triplek -> Pilih Plywood
     * (barang cacat yang sudah ditembel dan sekarang jadi bagus).
     */
    public function hasilTembelTriplek(): BelongsTo
    {
        return $this->belongsTo(HasilTembeltriplek::class, 'id_hasil_tembel_triplek');
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
     * - 'graji_triplek'   -> berasal dari Graji Triplek
     * - 'sanding'         -> berasal dari Sanding
     * - 'dempul'          -> berasal dari perbaikan Dempul
     * - 'tembel_triplek'  -> berasal dari perbaikan Tembel Triplek
     */
    public function getTipeSumberAttribute(): string
    {
        return match (true) {
            (bool) $this->id_hasil_graji_triplek => 'graji_triplek',
            (bool) $this->id_detail_dempul => 'dempul',
            (bool) $this->id_hasil_tembel_triplek => 'tembel_triplek',
            default => 'sanding',
        };
    }

    /**
     * Label asal barang yang sebenarnya, untuk ditampilkan di UI.
     */
    public function getAsalLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->id_hasil_graji_triplek => 'Graji Triplek',
            (bool) $this->id_hasil_sanding => 'Sanding',
            (bool) $this->id_detail_dempul => 'Dempul (Perbaikan)',
            (bool) $this->id_hasil_tembel_triplek => 'Tembel Triplek (Perbaikan)',
            default => '-',
        };
    }

    /**
     * Ambil record hasil produksi apapun sumbernya
     * (hasil Graji Triplek, Sanding, Dempul, atau Tembel Triplek).
     */
    public function getHasilAttribute()
    {
        return $this->hasilGrajiTriplek
            ?? $this->hasilSanding
            ?? $this->detailDempul
            ?? $this->hasilTembelTriplek;
    }

    /**
     * Barang setengah jadi terkait, terlepas dari nama relasi yang beda-beda
     * antar model hasil:
     * - HasilSanding & HasilGrajiTriplek/DetailDempul/HasilTembeltriplek
     *   sama-sama punya relasi barangSetengahJadi() / barangSetengahJadiHp(),
     *   jadi kita coba dua-duanya untuk jaga kompatibilitas nama.
     */
    public function getBarangSetengahJadiAttribute()
    {
        $hasil = $this->hasil;

        return $hasil?->barangSetengahJadi ?? $hasil?->barangSetengahJadiHp ?? null;
    }

    /**
     * Jumlah/isi barang, terlepas dari nama kolom yang beda-beda antar model
     * hasil:
     * - HasilGrajiTriplek -> `isi`
     * - HasilSanding      -> `kuantitas`
     * - DetailDempul & HasilTembeltriplek -> `hasil`
     *   (jumlah barang yang berhasil diperbaiki jadi bagus)
     */
    public function getJumlahAttribute()
    {
        $hasil = $this->hasil;

        return $hasil->isi
            ?? $hasil->kuantitas
            ?? $hasil->hasil
            ?? null;
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
     * `id_serah_terima_triplek_jadi` dan `jumlah` sebagai pencatat
     * pemakaian. Sesuaikan nama model/kolom bila berbeda di skema Anda.
     */
    public function getSisaAttribute(): float
    {
        $terpakai = BahanPilihPlywood::where('id_serah_terima_triplek_jadi', $this->id)
            ->sum('jumlah');

        return $this->qtyAsli - (float) $terpakai;
    }
}
