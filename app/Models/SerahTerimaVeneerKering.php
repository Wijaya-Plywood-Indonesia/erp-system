<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SerahTerimaVeneerKering extends Model
{
    protected $table = 'serah_terima_veneer_kering';

    protected $fillable = [
        'id_detail_hasil',
        'id_detail_bongkar_kedi',
        'id_hasil_sanding_joint',
        'id_mutasi_keluar_palet',
        'id_mutasi_keluar_palet_jadi',
        'tipe_sumber',
        'id_produksi_repair',
        'diserahkan_oleh',
        'diterima_oleh',
        'jenis_terima',
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

    public function hasilSandingJoint(): BelongsTo
    {
        return $this->belongsTo(HasilSandingJoint::class, 'id_hasil_sanding_joint');
    }

    /**
     * Sumber dari mutasi keluar Gudang Veneer Kering (per palet),
     * mis. dikeluarkan untuk kebutuhan Repair.
     */
    public function mutasiKeluarPalet(): BelongsTo
    {
        return $this->belongsTo(VeneerKeringMutasiKeluarPalet::class, 'id_mutasi_keluar_palet');
    }

    /**
     * 🆕 Sumber dari mutasi keluar Gudang Veneer JADI (per palet),
     * mis. dikeluarkan untuk kebutuhan Repair.
     */
    public function mutasiKeluarPaletJadi(): BelongsTo
    {
        return $this->belongsTo(VeneerJadiMutasiKeluarPalet::class, 'id_mutasi_keluar_palet_jadi');
    }

    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    /**
     * Semua pemakaian palet ini sebagai bahan (modal) Repair,
     * lintas produksi repair manapun.
     */
    public function modalRepairs(): HasMany
    {
        return $this->hasMany(ModalRepair::class, 'id_serah_terima_veneer_kering');
    }

    // ─────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────

    public function isMenunggu(): bool
    {
        return $this->diterima_oleh === '-';
    }

    /**
     * Ambil model sumber (DetailHasil, DetailBongkarKedi, HasilSandingJoint,
     * VeneerKeringMutasiKeluarPalet, atau VeneerJadiMutasiKeluarPalet) tanpa
     * perlu tahu tipe_sumber di luar model.
     */
    public function getSumberAttribute(): DetailHasil|DetailBongkarKedi|HasilSandingJoint|VeneerKeringMutasiKeluarPalet|VeneerJadiMutasiKeluarPalet|null
    {
        return match ($this->tipe_sumber) {
            'dryer' => $this->detailHasil,
            'kedi' => $this->detailBongkarKedi,
            'sanding_joint' => $this->hasilSandingJoint,
            'gudang' => $this->mutasiKeluarPalet,
            'gudang_jadi' => $this->mutasiKeluarPaletJadi,
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
            'sanding_joint' => 'Sanding Joint',
            'gudang' => 'Gudang Kering',
            'gudang_jadi' => 'Gudang Jadi',
            default => '-',
        };
    }

    /**
     * Label jenis penerimaan (Kering/Jadi)
     */
    public function getLabelJenisTerimaAttribute(): string
    {
        return match ($this->jenis_terima) {
            'kering' => 'Kering',
            'jadi' => 'Jadi',
            default => '-',
        };
    }

    /**
     * Qty asli dari sumber:
     * - DetailHasil                    -> isi
     * - DetailBongkarKedi              -> jumlah
     * - HasilSandingJoint              -> jumlah
     * - VeneerKeringMutasiKeluarPalet  -> qty
     * - VeneerJadiMutasiKeluarPalet    -> jumlah_lembar
     */
    public function getQtyAsliAttribute(): float
    {
        return (float) ($this->sumber->isi ?? $this->sumber->jumlah ?? $this->sumber->qty ?? $this->sumber->jumlah_lembar ?? 0);
    }

    /**
     * Total lembar yang sudah dipakai sebagai bahan (modal) Repair,
     * dijumlahkan lintas semua produksi repair.
     */
    public function getTotalDigunakanAttribute(): float
    {
        return (float) $this->modalRepairs()->sum('jumlah');
    }

    /**
     * Sisa lembar yang masih bisa dipakai sebagai bahan Repair.
     */
    public function getSisaAttribute(): float
    {
        return $this->qty_asli - $this->total_digunakan;
    }

    public function getTampilanAttribute(): array
    {
        return match ($this->tipe_sumber) {
            'dryer', 'kedi' => [
                'no_palet' => $this->sumber?->no_palet ?? '-',
                'dimensi' => $this->sumber?->ukuran
                    ? collect([$this->sumber->ukuran->panjang, $this->sumber->ukuran->lebar, $this->sumber->ukuran->tebal])
                        ->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))
                        ->implode('x')
                    : '-',
                'jenis_kayu' => $this->sumber?->jenisKayu?->nama_kayu ?? '-',
                'kw' => $this->sumber?->kw ?? '-',
            ],
            'gudang' => [
                'no_palet' => $this->mutasiKeluarPalet?->no_palet ?? '-',
                'dimensi' => $this->mutasiKeluarPalet?->mutasiKeluar?->ukuran
                    ? collect([
                        $this->mutasiKeluarPalet->mutasiKeluar->ukuran->panjang,
                        $this->mutasiKeluarPalet->mutasiKeluar->ukuran->lebar,
                        $this->mutasiKeluarPalet->mutasiKeluar->ukuran->tebal,
                    ])->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))
                        ->implode('x')
                    : '-',
                'jenis_kayu' => $this->mutasiKeluarPalet?->mutasiKeluar?->jenisKayu?->nama_kayu ?? '-',
                'kw' => $this->mutasiKeluarPalet?->mutasiKeluar?->kw ?? '-',
            ],
            'gudang_jadi' => [
                'no_palet' => $this->mutasiKeluarPaletJadi?->nomor_palet !== null
                    ? 'jd-'.$this->mutasiKeluarPaletJadi->nomor_palet
                    : '-',
                'dimensi' => $this->mutasiKeluarPaletJadi?->mutasiKeluar
                    ? collect([
                        $this->mutasiKeluarPaletJadi->mutasiKeluar->panjang,
                        $this->mutasiKeluarPaletJadi->mutasiKeluar->lebar,
                        $this->mutasiKeluarPaletJadi->mutasiKeluar->tebal,
                    ])->map(fn ($v) => rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.'))
                        ->implode('x')
                    : '-',
                'jenis_kayu' => $this->mutasiKeluarPaletJadi?->mutasiKeluar?->jenisKayu?->nama_kayu ?? '-',
                'kw' => $this->mutasiKeluarPaletJadi?->mutasiKeluar?->kw_grade ?? '-',
            ],
            default => ['no_palet' => '-', 'dimensi' => '-', 'jenis_kayu' => '-', 'kw' => '-'],
        };
    }
}
