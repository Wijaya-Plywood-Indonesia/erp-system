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
        'id_hasil_graji_triplek',
        'id_hasil_sanding',
        'id_triplek_mutasi_keluar',
        'id_platform_mth_mutasi_keluar',
        'id_triplek_mth_mutasi_keluar',
        'id_produksi_graji_triplek',
        'id_produksi_sanding',
        'diserahkan_oleh',
        'diterima_oleh',
        'status',
        'tujuan',
        // 🆕 Pengembalian sisa bahan ke gudang (lihat method kembaliKeGudang()
        // di SerahTerimaHpService, dan getSisaAttribute() di bawah).
        'jumlah_dikembalikan',
        'ditolak_oleh',
        'alasan_tolak',
        'ditolak_at',
    ];

    // 🆕 Supaya nilainya selalu float saat dibaca, bukan string.
    protected $casts = [
        'jumlah_dikembalikan' => 'decimal:2',
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

    /**
     * Hasil produksi Graji Triplek, dipakai saat serah manual Graji -> Sanding.
     */
    public function hasilGrajiTriplek(): BelongsTo
    {
        return $this->belongsTo(HasilGrajiTriplek::class, 'id_hasil_graji_triplek');
    }

    /**
     * Hasil produksi Sanding, dipakai saat serah manual Sanding -> Graji.
     */
    public function hasilSanding(): BelongsTo
    {
        return $this->belongsTo(HasilSanding::class, 'id_hasil_sanding');
    }

    /**
     * Mutasi keluar dari Gudang Triplek Jadi. Terisi hanya untuk barang yang
     * dikeluarkan dari stok triplek jadi dengan tujuan Produksi Sanding.
     */
    public function triplekMutasiKeluar(): BelongsTo
    {
        return $this->belongsTo(TriplekJadiMutasiKeluar::class, 'id_triplek_mutasi_keluar');
    }

    /**
     * Mutasi keluar dari Gudang Platform Mentah. Terisi hanya untuk barang
     * yang dikeluarkan dari stok platform mentah dengan tujuan Produksi Sanding.
     */
    public function platformMthMutasiKeluar(): BelongsTo
    {
        return $this->belongsTo(PlatformMthMutasiKeluar::class, 'id_platform_mth_mutasi_keluar');
    }

    /**
     * Mutasi keluar dari Gudang Triplek Mentah. Terisi hanya untuk barang
     * yang dikeluarkan dari stok triplek mentah dengan tujuan Produksi Graji Triplek.
     */
    public function triplekMthMutasiKeluar(): BelongsTo
    {
        return $this->belongsTo(TriplekMthMutasiKeluar::class, 'id_triplek_mth_mutasi_keluar');
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
     * Tipe sumber untuk keperluan hitungan stok/sisa (bukan asal literalnya):
     * - 'triplek' -> barang ini menuju Graji Triplek (dari HP atau dari Sanding manual)
     * - 'platform' -> barang ini menuju Sanding (dari HP, dari Graji manual,
     *   atau dari Gudang Platform Mentah)
     * - 'triplek' -> barang ini menuju Graji Triplek (dari HP, dari Sanding
     *   manual, atau dari Gudang Triplek Mentah)
     */
    public function getTipeSumberAttribute(): string
    {
        if ($this->id_platform_hasil_hp || $this->id_hasil_graji_triplek || $this->id_platform_mth_mutasi_keluar) {
            return 'platform';
        }

        return 'triplek';
    }

    /**
     * Label asal barang yang sebenarnya, untuk ditampilkan di UI.
     */
    public function getAsalLabelAttribute(): string
    {
        return match (true) {
            (bool) $this->id_triplek_hasil_hp => 'Hotpress',
            (bool) $this->id_platform_hasil_hp => 'Hotpress',
            (bool) $this->id_hasil_graji_triplek => 'Graji Triplek',
            (bool) $this->id_hasil_sanding => 'Sanding',
            (bool) $this->id_triplek_mutasi_keluar => 'Gudang Triplek Jadi',
            (bool) $this->id_platform_mth_mutasi_keluar => 'Gudang Platform Mentah',
            (bool) $this->id_triplek_mth_mutasi_keluar => 'Gudang Triplek Mentah',
            default => '-',
        };
    }

    /**
     * 🆕 Apakah baris ini berasal dari salah satu gudang (Triplek Jadi /
     * Platform Mentah / Triplek Mentah) — bukan dari WIP internal
     * (Hotpress/Graji/Sanding). Hanya sumber gudang yang stoknya betul-betul
     * berkurang saat diserahkan, jadi hanya sumber ini yang bisa
     * "dikembalikan ke gudang".
     */
    public function isDariGudang(): bool
    {
        return $this->id_triplek_mutasi_keluar !== null
            || $this->id_platform_mth_mutasi_keluar !== null
            || $this->id_triplek_mth_mutasi_keluar !== null;
    }

    /**
     * 🆕 Ambil model mutasi keluar gudang yang relevan (TriplekJadiMutasiKeluar
     * / PlatformMthMutasiKeluar / TriplekMthMutasiKeluar), atau null kalau
     * baris ini bukan dari gudang (lihat isDariGudang()).
     */
    public function mutasiGudang(): TriplekJadiMutasiKeluar|PlatformMthMutasiKeluar|TriplekMthMutasiKeluar|null
    {
        if ($this->id_triplek_mutasi_keluar !== null) {
            return $this->triplekMutasiKeluar;
        }

        if ($this->id_platform_mth_mutasi_keluar !== null) {
            return $this->platformMthMutasiKeluar;
        }

        if ($this->id_triplek_mth_mutasi_keluar !== null) {
            return $this->triplekMthMutasiKeluar;
        }

        return null;
    }

    /**
     * 🆕 Kunci sumber gudang ('triplek_jadi' | 'platform_mth' | 'triplek_mth'),
     * atau null kalau bukan dari gudang. Dipakai SerahTerimaHpService untuk
     * memilih Stok*Service yang benar.
     */
    public function sumberGudangKey(): ?string
    {
        return match (true) {
            $this->id_triplek_mutasi_keluar !== null => 'triplek_jadi',
            $this->id_platform_mth_mutasi_keluar !== null => 'platform_mth',
            $this->id_triplek_mth_mutasi_keluar !== null => 'triplek_mth',
            default => null,
        };
    }

    /**
     * Ambil record hasil produksi apapun sumbernya
     * (triplek HP, platform HP, hasil Graji Triplek, hasil Sanding,
     * mutasi keluar Gudang Platform Mentah, atau mutasi keluar
     * Gudang Triplek Mentah).
     */
    public function getHasilAttribute()
    {
        return $this->triplekHasilHp
            ?? $this->platformHasilHp
            ?? $this->hasilGrajiTriplek
            ?? $this->hasilSanding
            ?? $this->platformMthMutasiKeluar
            ?? $this->triplekMthMutasiKeluar;
    }

    /**
     * Barang setengah jadi terkait, terlepas dari nama relasi yang beda-beda
     * antar model hasil (TriplekHasilHp/PlatformHasilHp/HasilSanding pakai
     * `barangSetengahJadi`, HasilGrajiTriplek pakai `barangSetengahJadiHp`).
     * Untuk Gudang Platform Mentah tidak ada barang setengah jadi (langsung
     * jenis kayu), jadi null.
     */
    public function getBarangSetengahJadiAttribute()
    {
        $hasil = $this->hasil;

        return $hasil?->barangSetengahJadi ?? $hasil?->barangSetengahJadiHp ?? null;
    }

    /**
     * Jumlah/isi barang.
     * - Dari Gudang Triplek Jadi: pakai stok_lembar mutasi keluar.
     * - Dari Gudang Platform Mentah: pakai stok_lembar mutasi keluar.
     * - Selain itu (TriplekHasilHp/PlatformHasilHp/HasilGrajiTriplek pakai `isi`,
     *   HasilSanding pakai `kuantitas`).
     */
    public function getJumlahAttribute()
    {
        if ($this->id_triplek_mutasi_keluar !== null) {
            return $this->triplekMutasiKeluar->stok_lembar ?? null;
        }

        if ($this->id_platform_mth_mutasi_keluar !== null) {
            return $this->platformMthMutasiKeluar->stok_lembar ?? null;
        }

        if ($this->id_triplek_mth_mutasi_keluar !== null) {
            return $this->triplekMthMutasiKeluar->stok_lembar ?? null;
        }

        return $this->hasil->isi ?? $this->hasil->kuantitas ?? null;
    }

    public function isMenunggu(): bool
    {
        return $this->diterima_oleh === '-';
    }

    public function isDitolak(): bool
    {
        return $this->ditolak_oleh !== null;
    }

    public function getLabelStatusAttribute(): string
    {
        return $this->isDitolak()
            ? 'Ditolak'
            : ($this->isMenunggu() ? 'Menunggu' : 'Diterima');
    }

    public function getQtyAsliAttribute(): float
    {
        return (float) ($this->jumlah ?? 0);
    }

    /**
     * Sisa = qty asli dikurangi total pemakaian, dikurangi lagi total yang
     * sudah dikembalikan ke gudang.
     * - Menuju triplek (Graji): pemakaian dihitung dari MasukGrajiTriplek.
     * - Menuju platform (Sanding): pemakaian dihitung dari ModalSanding.
     *
     * 🆕 Ditambah `- jumlah_dikembalikan` supaya konsisten dengan pola
     * VeneerJadiMutasiKeluarPalet::sisa di fitur Hotpress: begitu sebagian
     * sudah dikembalikan, sisa yang BISA dikembalikan lagi otomatis berkurang
     * (tidak bisa dikembalikan dua kali untuk jumlah yang sama).
     */
    public function getSisaAttribute(): float
    {
        // 🆕 Bahan yang menuju Sanding (hasil Hotpress, hasil Graji, Gudang
        // Platform Mentah, DAN Gudang Triplek Jadi) dihitung dari ModalSanding.
        // Sebelumnya Gudang Triplek Jadi ikut terhitung 'triplek' sehingga
        // pemakaiannya dicari di MasukGrajiTriplek dan sisanya tidak pernah
        // berkurang walau sudah dipakai sebagai modal.
        $menujuSanding = $this->tipeSumber === 'platform'
            || $this->id_triplek_mutasi_keluar !== null;

        $terpakai = $menujuSanding
            ? ModalSanding::where('id_serah_terima_hp', $this->id)->sum('kuantitas')
            : MasukGrajiTriplek::where('id_serah_terima_hp', $this->id)->sum('isi');

        return $this->qtyAsli - (float) $terpakai - (float) $this->jumlah_dikembalikan;
    }

    public function serahTerimaHp()
    {
        return $this->hasOne(SerahTerimaHp::class, 'id_produksi_graji_triplek');
    }
}
