<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StokVeneerKering extends Model
{
    protected $table = 'stok_veneer_kerings';

    protected $fillable = [
        'id_produksi_dryer',
        'id_detail_hasil_dryer',
        'id_ukuran',
        'id_jenis_kayu',
        'kw',
        'jenis_transaksi',
        'tanggal_transaksi',
        'qty',
        'm3',
        'hpp_veneer_basah_per_m3',
        'ongkos_dryer_per_m3',
        'hpp_kering_per_m3',
        'nilai_transaksi',
        'stok_m3_sebelum',
        'nilai_stok_sebelum',
        'stok_m3_sesudah',
        'nilai_stok_sesudah',
        'hpp_average',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_transaksi' => 'date',
        'qty' => 'decimal:4',
        'm3' => 'decimal:6',
        'hpp_veneer_basah_per_m3' => 'decimal:4',
        'ongkos_dryer_per_m3' => 'decimal:4',
        'hpp_kering_per_m3' => 'decimal:4',
        'nilai_transaksi' => 'decimal:4',
        'stok_m3_sebelum' => 'decimal:6',
        'nilai_stok_sebelum' => 'decimal:4',
        'stok_m3_sesudah' => 'decimal:6',
        'nilai_stok_sesudah' => 'decimal:4',
        'hpp_average' => 'decimal:4',
    ];

    // ─── Relasi ──────────────────────────────────────────────────────────────

    public function produksi()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    // ─── Scope ───────────────────────────────────────────────────────────────

    /**
     * Filter query untuk kombinasi produk tertentu.
     * Dipakai di snapshotTerakhir() dan rebuildStokDariTanggal() di service.
     */
    public function scopeForProduk($query, int $idUkuran, int $idJenisKayu, string $kw)
    {
        return $query
            ->where('id_ukuran', $idUkuran)
            ->where('id_jenis_kayu', $idJenisKayu)
            ->where('kw', $kw);
    }

    // ─── Static Helper ───────────────────────────────────────────────────────

    /**
     * Ambil snapshot stok terakhir untuk kombinasi produk tertentu.
     *
     * @param string|null $sebelumTanggal  Jika diisi, hanya ambil baris
     *                                     sebelum tanggal ini. Dipakai saat
     *                                     rebuild untuk menentukan titik awal.
     */
    public static function snapshotTerakhir(
        int $idUkuran,
        int $idJenisKayu,
        string $kw,
        ?string $sebelumTanggal = null
    ): array {
        $query = static::forProduk($idUkuran, $idJenisKayu, $kw);

        if ($sebelumTanggal) {
            $query->whereDate('tanggal_transaksi', '<', $sebelumTanggal);
        }

        $last = $query
            ->orderByDesc('tanggal_transaksi')
            ->orderByDesc('id')
            ->first();

        return [
            'stok_m3' => $last ? (float) $last->stok_m3_sesudah : 0.0,
            'nilai_stok' => $last ? (float) $last->nilai_stok_sesudah : 0.0,
            'hpp_average' => $last ? (float) $last->hpp_average : 0.0,
        ];
    }
}
