<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferensiHargaProduksi extends Model
{
    use HasFactory;

    protected $table = 'referensi_harga_produksi';

    protected $fillable = [
        'nama',
        'id_ukuran',
        'id_jenis_kayu',
        'id_sub_anak_akun',
        'jenis_barang',
        'kw',
        'harga',
    ];

    protected $casts = [
        'id_ukuran' => 'integer',
        'id_jenis_kayu' => 'integer',
        'id_sub_anak_akun' => 'integer',
        'harga' => 'float',
    ];

    /**
     * Relasi ke model Ukuran
     */
    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    /**
     * Relasi ke model JenisKayu
     */
    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    /**
     * Relasi ke model SubAnakAkun
     */
    public function subAnakAkun(): BelongsTo
    {
        return $this->belongsTo(SubAnakAkun::class, 'id_sub_anak_akun');
    }

    /**
     * Cari referensi harga produksi dengan pencarian berbasis ukuran spesifik.
     * Jika tidak ditemukan, akan fallback mencari dengan id_ukuran = null (referensi harga awal/standar).
     *
     * Contoh penggunaan:
     * $ref = ReferensiHargaProduksi::findReferensi($idJenisKayu, $jenisBarang, $kw, $idUkuran);
     * $harga = $ref?->harga ?? 0;
     */
    public static function findReferensi(?int $idJenisKayu, ?string $jenisBarang, ?string $kw, ?int $idUkuran = null): ?self
    {
        // 1. Cari yang cocok dengan id_ukuran spesifik terlebih dahulu
        if ($idUkuran) {
            $record = self::where('id_jenis_kayu', $idJenisKayu)
                ->where('jenis_barang', $jenisBarang)
                ->where('kw', $kw)
                ->where('id_ukuran', $idUkuran)
                ->first();

            if ($record) {
                return $record;
            }
        }

        // 2. Fallback: Cari yang id_ukuran-nya null (referensi awal/standar)
        return self::where('id_jenis_kayu', $idJenisKayu)
            ->where('jenis_barang', $jenisBarang)
            ->where('kw', $kw)
            ->whereNull('id_ukuran')
            ->first();
    }
}
