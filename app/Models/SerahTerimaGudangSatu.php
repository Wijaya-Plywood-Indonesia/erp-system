<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaGudangSatu extends Model
{
    protected $table = 'serah_terima_gudang_satu';

    protected $fillable = [
        'id_hasil_pilih_plywood',
        'id_produksi_terima_gudang_satu',
        'diserahkan_oleh',
        'diterima_oleh',
        'status',
    ];

    // ─────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────

    /**
     * Hasil produksi Pilih Plywood, sumber tunggal barang yang diserahkan.
     */
    public function hasilPilihPlywood(): BelongsTo
    {
        return $this->belongsTo(HasilPilihPlywood::class, 'id_hasil_pilih_plywood');
    }

    /**
     * Produksi Terima Gudang Satu, tujuan penyerahan barang ini.
     */
    public function produksiTerimaGudangSatu(): BelongsTo
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Barang setengah jadi terkait, diambil dari hasil Pilih Plywood.
     */
    public function getBarangSetengahJadiAttribute()
    {
        return $this->hasilPilihPlywood?->barangSetengahJadiHp;
    }

    /**
     * Jumlah/isi barang, mengikuti kolom `jumlah_bagus` di HasilPilihPlywood
     * (pakai jumlah_bagus karena itu yang layak diserahkan, bukan jumlah cacat).
     */
    public function getJumlahAttribute()
    {
        return $this->hasilPilihPlywood->jumlah_bagus ?? null;
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
     * Sisa = qty asli dikurangi total pemakaian di Terima Gudang Satu.
     *
     * NOTE: asumsi ada model `BahanTerimaGudangSatu` dengan kolom
     * `id_serah_terima_gudang_satu` dan `kuantitas` sebagai pencatat
     * pemakaian. Sesuaikan nama model/kolom bila berbeda di skema Anda.
     */
    public function getSisaAttribute(): float
    {
        $terpakai = BahanTerimaGudangSatu::where('id_serah_terima_gudang_satu', $this->id)
            ->sum('jumlah');

        return $this->qtyAsli - (float) $terpakai;
    }
}
