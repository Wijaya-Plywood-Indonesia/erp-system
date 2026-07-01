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
        'id_kategori_barang',
        'id_grade',
        'kw_min',
        'kw_max',
        't_min',
        't_max',
        'harga',
        'id_sub_anak_akun',
        'created_by',
    ];

    protected $casts = [
        'id_ukuran' => 'integer',
        'id_jenis_kayu' => 'integer',
        'id_kategori_barang' => 'integer',
        'id_grade' => 'integer',
        'kw_min' => 'integer',
        'kw_max' => 'integer',
        't_min' => 'float',
        't_max' => 'float',
        'harga' => 'float',
        'id_sub_anak_akun' => 'integer',
        'created_by' => 'integer',
    ];

    // ── Relasi ────────────────────────────────────────────────

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function kategoriBarang(): BelongsTo
    {
        return $this->belongsTo(KategoriBarang::class, 'id_kategori_barang');
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class, 'id_grade');
    }

    public function subAnakAkun(): BelongsTo
    {
        return $this->belongsTo(SubAnakAkun::class, 'id_sub_anak_akun');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // ── Static finder ─────────────────────────────────────────

    /**
     * Cari referensi harga produksi berdasarkan parameter.
     *
     * Logika pencarian:
     *  1. Cocokkan semua parameter + id_ukuran spesifik
     *  2. Fallback: id_ukuran = null (referensi standar)
     *
     * Parameter kw dan tebal dicocokkan ke dalam range (min–max).
     * Jika kolom min/max null di database, dianggap tidak ada batasan (wildcard).
     *
     * Contoh:
     *   $ref = ReferensiHargaProduksi::findReferensi(
     *       idJenisKayu: 1,
     *       idKategoriBarang: 2,
     *       idGrade: 1,
     *       kw: 3,
     *       tebal: 2.5,
     *       idUkuran: 5,
     *   );
     *   $harga = $ref?->harga ?? 0;
     */
    public static function findReferensi(
        ?int $idJenisKayu = null,
        ?int $idKategoriBarang = null,
        ?int $idGrade = null,
        ?int $kw = null,
        ?float $tebal = null,
        ?int $idUkuran = null,
        ?int $idSubAnakAkun = null,
    ): ?self {
        $base = fn () => self::query()
            ->when($idJenisKayu !== null, fn ($q) => $q->where('id_jenis_kayu', $idJenisKayu))
            ->when($idKategoriBarang !== null, fn ($q) => $q->where('id_kategori_barang', $idKategoriBarang))
            ->when($idGrade !== null, fn ($q) => $q->where('id_grade', $idGrade))
            ->when($idSubAnakAkun !== null, fn ($q) => $q->where('id_sub_anak_akun', $idSubAnakAkun))
            // kw harus berada dalam range kw_min – kw_max
            ->when($kw !== null, fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('kw_min')->orWhere('kw_min', '<=', $kw))
                ->where(fn ($q2) => $q2->whereNull('kw_max')->orWhere('kw_max', '>=', $kw))
            )
            // tebal harus berada dalam range t_min – t_max
            ->when($tebal !== null, fn ($q) => $q
                ->where(fn ($q2) => $q2->whereNull('t_min')->orWhere('t_min', '<=', $tebal))
                ->where(fn ($q2) => $q2->whereNull('t_max')->orWhere('t_max', '>=', $tebal))
            );

        // 1. Cari dengan id_ukuran spesifik
        if ($idUkuran !== null) {
            $record = $base()->where('id_ukuran', $idUkuran)->first();
            if ($record) {
                return $record;
            }
        }

        // 2. Fallback: id_ukuran null (referensi standar)
        return $base()->whereNull('id_ukuran')->first();
    }
}
