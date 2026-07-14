<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaTriplekCacat extends Model
{
    protected $table = 'serah_terima_triplek_cacat';

    protected $fillable = [
        'id_hasil_pilih_plywood',
        'id_produksi_dempul',
        'id_produksi_tembel_triplek',
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

    /**
     * Terisi hanya jika tujuan = 'dempul'.
     */
    public function produksiDempul(): BelongsTo
    {
        return $this->belongsTo(ProduksiDempul::class, 'id_produksi_dempul');
    }

    /**
     * Terisi hanya jika tujuan = 'tembel_triplek'.
     */
    public function produksiTembelTriplek(): BelongsTo
    {
        return $this->belongsTo(ProduksiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function getHasilAttribute()
    {
        return $this->hasilPilihPlywood;
    }

    public function getAsalLabelAttribute(): string
    {
        return $this->hasilPilihPlywood ? 'Pilih Plywood' : '-';
    }

    /**
     * Record produksi tujuan aktual, mengikuti kolom `tujuan`
     * (mutually exclusive per row).
     */
    public function getTujuanProduksiAttribute()
    {
        return match ($this->tujuan) {
            'dempul' => $this->produksiDempul,
            'tembel_triplek' => $this->produksiTembelTriplek,
            default => null,
        };
    }

    public function getLabelTujuanAttribute(): string
    {
        return match ($this->tujuan) {
            'dempul' => 'Dempul',
            'tembel_triplek' => 'Tembel Triplek',
            default => '-',
        };
    }

    public function getBarangSetengahJadiAttribute()
    {
        return $this->hasilPilihPlywood?->barangSetengahJadiHp;
    }

    /**
     * Jumlah barang CACAT yang diserahkan, diambil dari `jumlah`
     * (kolom cacat) pada HasilPilihPlywood.
     *
     * PENTING: bukan jumlah_bagus — itu kolom untuk barang bagus dan
     * dipakai di SerahTerimaGudangSatu, bukan di sini. Model ini
     * merepresentasikan serah-terima barang cacat.
     */
    public function getJumlahAttribute()
    {
        return $this->hasilPilihPlywood->jumlah ?? null;
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
     * Pemakaian dihitung dari sumber yang berbeda tergantung `tujuan`:
     * - dempul          -> BahanDempul (kolom `jumlah`)
     * - tembel_triplek  -> HasilTembeltriplek (kolom `modal`)
     */
    public function getSisaAttribute(): float
    {
        $terpakai = match ($this->tujuan) {
            'dempul' => DetailDempul::where('id_serah_terima_triplek_cacat', $this->id)->sum('modal'),
            'tembel_triplek' => HasilTembeltriplek::where('id_serah_terima_triplek_cacat', $this->id)->sum('modal'),
            default => 0,
        };

        return $this->qtyAsli - (float) $terpakai;
    }

    /**
     * Pastikan hanya salah satu id_produksi_* yang terisi sesuai tujuan.
     */
    public function isTujuanValid(): bool
    {
        return match ($this->tujuan) {
            'dempul' => filled($this->id_produksi_dempul) && blank($this->id_produksi_tembel_triplek),
            'tembel_triplek' => filled($this->id_produksi_tembel_triplek) && blank($this->id_produksi_dempul),
            default => false,
        };
    }
}
