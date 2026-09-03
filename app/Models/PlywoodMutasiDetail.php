<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlywoodMutasiDetail extends Model
{
    protected $table = 'plywood_mutasi_details';

    protected $fillable = [
        'id_plywood_mutasi', 'id_ukuran', 'id_jenis_kayu',
        'kw_grade', 'qty', 'm3', 'harga',
    ];

    protected $casts = [
        'qty' => 'integer',
        'm3' => 'float',
        'harga' => 'float',
    ];

    public const M3_DIVISOR = 10_000_000;

    public static function hitungM3(Ukuran $u, int $qty): float
    {
        return ($u->panjang * $u->lebar * $u->tebal * $qty) / self::M3_DIVISOR;
    }

    public function mutasi(): BelongsTo
    {
        return $this->belongsTo(PlywoodMutasi::class, 'id_plywood_mutasi');
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    /**
     * Relasi ke BarangSetengahJadiHp berdasarkan dimensi ukuran, jenis kayu, dan kw_grade.
     */
    public function getBarangAttribute(): ?BarangSetengahJadiHp
    {
        $ukuran = $this->ukuran ?? ($this->id_ukuran ? Ukuran::find($this->id_ukuran) : null);
        if (! $ukuran) {
            return null;
        }

        $matchedUkuranIds = Ukuran::where('tebal', $ukuran->tebal)
            ->where(function ($q) use ($ukuran) {
                $q->where(fn ($s) => $s->where('panjang', $ukuran->panjang)->where('lebar', $ukuran->lebar))
                    ->orWhere(fn ($s) => $s->where('panjang', $ukuran->lebar)->where('lebar', $ukuran->panjang));
            })->pluck('id');

        $grade = Grade::whereRaw('LOWER(TRIM(nama_grade)) = ?', [strtolower(trim($this->kw_grade))])
            ->whereHas('kategoriBarang', fn ($q) => $q->where('nama_kategori', 'like', '%plywood%'))
            ->first()
            ?? Grade::whereRaw('LOWER(TRIM(nama_grade)) = ?', [strtolower(trim($this->kw_grade))])->first();

        $jenisBarang = JenisBarang::where('nama_jenis_barang', 'like', $this->jenisKayu?->nama_kayu)->first()
            ?? JenisBarang::find($this->id_jenis_kayu);

        $bshp = BarangSetengahJadiHp::with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
            ->whereIn('id_ukuran', $matchedUkuranIds)
            ->when($jenisBarang, fn ($q) => $q->where('id_jenis_barang', $jenisBarang->id))
            ->when($grade, fn ($q) => $q->where('id_grade', $grade->id))
            ->first();

        if (! $bshp) {
            $bshp = BarangSetengahJadiHp::with(['ukuran', 'jenisBarang', 'grade.kategoriBarang'])
                ->whereIn('id_ukuran', $matchedUkuranIds)
                ->when($grade, fn ($q) => $q->where('id_grade', $grade->id))
                ->first();
        }

        return $bshp;
    }

    public function getNamaBarangAttribute(): string
    {
        if ($this->barang && $this->barang->label) {
            return $this->barang->label;
        }

        $parts = [
            'Plywood',
            $this->jenisKayu?->nama_kayu,
            $this->ukuran?->nama_ukuran,
            $this->kw_grade ? "KW {$this->kw_grade}" : null,
        ];

        return implode(' - ', array_filter($parts));
    }

    /**
     * Harga: pakai nilai manual di record ini kalau ada,
     * kalau tidak ambil dari data master BarangSetengahJadiHp.
     * Tidak ada fallback hardcode — kalau keduanya kosong, berarti 0
     * (data belum lengkap, bukan ditutupi angka default).
     */
    public function getHargaAttribute(): float
    {
        if (filled($this->attributes['harga'] ?? null) && (float) $this->attributes['harga'] > 0) {
            return (float) $this->attributes['harga'];
        }

        if ($this->barang && filled($this->barang->harga) && (float) $this->barang->harga > 0) {
            return (float) $this->barang->harga;
        }

        return 0.0;
    }

    public function getSatuanAttribute(): string
    {
        return 'Lembar';
    }
}
