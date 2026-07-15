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
        'id_hasil_terima_gudang_satu',
        'id_triplek_mutasi_keluar',
        'id_produksi_nyusup',
        'tujuan',
        'diserahkan_oleh',
        'diterima_oleh',
        'status',
    ];

    // ─────────────────────────────────────────────
    // Relations
    // ─────────────────────────────────────────────

    public function hasilPilihPlywood(): BelongsTo
    {
        return $this->belongsTo(HasilPilihPlywood::class, 'id_hasil_pilih_plywood');
    }

    public function produksiTerimaGudangSatu(): BelongsTo
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function hasilTerimaGudangSatu(): BelongsTo
    {
        return $this->belongsTo(HasilTerimaGudangSatu::class, 'id_hasil_terima_gudang_satu');
    }

    public function produksiNyusup(): BelongsTo
    {
        return $this->belongsTo(ProduksiNyusup::class, 'id_produksi_nyusup');
    }

    public function triplekMutasiKeluar(): BelongsTo
{
    return $this->belongsTo(\App\Models\TriplekJadiMutasiKeluar::class, 'id_triplek_mutasi_keluar');
}

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    /**
     * Sumber barang aktual: dari Pilih Plywood ATAU dari Hasil Terima Gudang Satu,
     * tergantung mana yang terisi (mutually exclusive tergantung `tujuan`).
     */
    public function getSumberAttribute()
    {
        return $this->hasilPilihPlywood ?? $this->hasilTerimaGudangSatu;
    }

    /**
     * Barang setengah jadi terkait. Fallback: kalau dari Pilih Plywood pakai
     * relasi barangSetengahJadiHp, kalau dari Hasil Terima Gudang Satu,
     * bentuk objek serupa dari grade/jenisBarang/ukuran langsung.
     */
    public function getBarangSetengahJadiAttribute()
    {
        if ($this->hasilPilihPlywood) {
            return $this->hasilPilihPlywood->barangSetengahJadiHp;
        }

        // HasilTerimaGudangSatu tidak punya barangSetengahJadiHp,
        // tapi punya grade/jenisBarang/ukuran langsung.
        return $this->hasilTerimaGudangSatu;
    }

    /**
     * Jumlah/isi barang.
     * - Dari Pilih Plywood: pakai `jumlah_bagus`.
     * - Dari Hasil Terima Gudang Satu: pakai `jumlah`.
     */
    public function getJumlahAttribute()
{
    if ($this->id_triplek_mutasi_keluar !== null) {
        return $this->triplekMutasiKeluar->stok_lembar ?? null;
    }

    if ($this->hasilPilihPlywood) {
        return $this->hasilPilihPlywood->jumlah_bagus ?? null;
    }

    return $this->hasilTerimaGudangSatu->jumlah ?? null;
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
     * Sisa = qty asli dikurangi total pemakaian.
     * Pemakaian bisa berasal dari 2 sumber tergantung tujuan:
     * - BahanTerimaGudangSatu (jalur produksi biasa)
     * - DetailBarangDikerjakan (jalur nyusup, pakai kolom `modal`)
     */
    public function getSisaAttribute(): float
    {
        $terpakaiBahan = BahanTerimaGudangSatu::where('id_serah_terima_gudang_satu', $this->id)
            ->sum('jumlah');

        $terpakaiNyusup = DetailBarangDikerjakan::where('id_serah_terima_gudang_satu', $this->id)
            ->sum('modal');

        return $this->qtyAsli - (float) $terpakaiBahan - (float) $terpakaiNyusup;
    }
}
