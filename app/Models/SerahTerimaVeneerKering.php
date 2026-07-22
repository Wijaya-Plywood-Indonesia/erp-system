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
        'id_hasil_joint',
        'id_mutasi_keluar_palet',
        'id_mutasi_keluar_palet_jadi',
        'tipe_sumber',
        'id_produksi_repair',
        'id_produksi_joint',
        'diserahkan_oleh',
        'diterima_oleh',
        'jenis_terima',
        'tujuan',
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

    /**
     * Sumber dari Produksi Joint (tabel: hasil_joint).
     *
     * ⚠️ PENTING: kolom id_hasil_joint SELALU berisi hasil_joint.id —
     * BUKAN hasil_sanding_joint.id. Sanding Joint tidak pernah melewati
     * alur serah terima ini. Sebelumnya relasi ini salah menunjuk ke
     * HasilSandingJoint sehingga id 147 dari hasil_joint dibaca sebagai
     * baris 147 di hasil_sanding_joint → jenis kayu & jumlah lembar acak.
     */
    public function hasilJoint(): BelongsTo
    {
        return $this->belongsTo(HasilJoint::class, 'id_hasil_joint');
    }

    /**
     * @deprecated Nama lama peninggalan era "Sanding Joint" dan targetnya salah.
     *             Sudah diarahkan ulang ke hasilJoint() supaya kode/Blade lama
     *             tidak error dan langsung menampilkan data yang benar.
     *             Hapus method ini setelah `grep -rn "hasilSandingJoint" app/ resources/`
     *             bersih.
     */
    public function hasilSandingJoint(): BelongsTo
    {
        return $this->hasilJoint();
    }

    public function mutasiKeluarPalet(): BelongsTo
    {
        return $this->belongsTo(VeneerKeringMutasiKeluarPalet::class, 'id_mutasi_keluar_palet');
    }

    public function mutasiKeluarPaletJadi(): BelongsTo
    {
        return $this->belongsTo(VeneerJadiMutasiKeluarPalet::class, 'id_mutasi_keluar_palet_jadi');
    }

    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

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

    public function getSumberAttribute(): DetailHasil|DetailBongkarKedi|HasilSandingJoint|VeneerKeringMutasiKeluarPalet|VeneerJadiMutasiKeluarPalet|null
    {
        return match ($this->tipe_sumber) {
            'dryer' => $this->detailHasil,
            'kedi' => $this->detailBongkarKedi,
            'joint' => $this->hasilSandingJoint,
            'gudang' => $this->mutasiKeluarPalet,
            'gudang_jadi' => $this->mutasiKeluarPaletJadi,
            default => null,
        };
    }

    public function getLabelSumberAttribute(): string
    {
        return match ($this->tipe_sumber) {
            'dryer' => 'Press Dryer',
            'kedi' => 'Kedi',
            'joint' => 'Sanding Joint',
            'gudang' => 'Gudang Kering',
            'gudang_jadi' => 'Gudang Jadi',
            default => '-',
        };
    }

    public function getLabelJenisTerimaAttribute(): string
    {
        return match ($this->jenis_terima) {
            'kering' => 'Kering',
            'jadi' => 'Jadi',
            default => '-',
        };
    }

    /**
     * ✅ FIX: qty asli sekarang di-resolve EKSPLISIT per tipe_sumber,
     * bukan lagi rantai `??` yang bisa berhenti di kolom `0`/'' sebelum
     * mencapai `jumlah_lembar` (kasus gudang_jadi).
     */
    public function getQtyAsliAttribute(): float
    {
        return (float) match ($this->tipe_sumber) {
            'dryer' => $this->sumber?->isi ?? 0,
            'kedi',
            'joint' => $this->sumber?->jumlah ?? 0,
            'gudang' => $this->sumber?->qty ?? 0,
            'gudang_jadi' => $this->sumber?->jumlah_lembar ?? 0,
            default => 0,
        };
    }

    public function getTotalDigunakanAttribute(): float
    {
        $totalRepair = (float) $this->modalRepairs()->sum('jumlah');
        $totalJoint = (float) $this->modalJoints()->sum('jumlah');

        return $totalRepair + $totalJoint;
    }

    public function getSisaAttribute(): float
    {
        return $this->qty_asli - $this->total_digunakan;
    }

    public function getTampilanAttribute(): array
    {
        return match ($this->tipe_sumber) {
            'dryer', 'kedi', 'joint' => [
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

    public static function cariUkuran($panjang, $lebar, $tebal): ?int
    {
        if ($panjang === null || $lebar === null || $tebal === null) {
            return null;
        }

        $p = round((float) $panjang, 2);
        $l = round((float) $lebar, 2);
        $t = round((float) $tebal, 2);

        return Ukuran::all()
            ->first(function ($u) use ($p, $l, $t) {
                return round((float) $u->panjang, 2) === $p
                    && round((float) $u->lebar, 2) === $l
                    && round((float) $u->tebal, 2) === $t;
            })?->id;
    }

    public function modalJoints(): HasMany
    {
        return $this->hasMany(ModalJoint::class, 'id_serah_terima_veneer_kering');
    }
}
