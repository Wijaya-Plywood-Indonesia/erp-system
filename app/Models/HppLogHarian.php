<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HppLogHarian extends Model
{
    protected $table = 'hpp_log_veneer_kering';

    protected $fillable = [
        'tanggal',
        'id_ukuran',
        'id_jenis_kayu',
        'kw',
        'total_lembar_masuk',
        'total_lembar_keluar',
        'stok_awal_lembar',
        'stok_akhir_lembar',
        'total_m3_masuk',
        'total_m3_keluar',
        'stok_akhir_m3',
        'hpp_veneer_basah_per_m3',
        'avg_ongkos_dryer_per_m3',
        'hpp_kering_per_m3',
        'hpp_average',
        'nilai_stok_akhir',
    ];

    protected $casts = [
        'tanggal'                 => 'date',
        'total_lembar_masuk'      => 'integer',
        'total_lembar_keluar'     => 'integer',
        'stok_awal_lembar'        => 'integer',
        'stok_akhir_lembar'       => 'integer',
        'total_m3_masuk'          => 'decimal:6',
        'total_m3_keluar'         => 'decimal:6',
        'stok_akhir_m3'           => 'decimal:6',
        'hpp_veneer_basah_per_m3' => 'decimal:4',
        'avg_ongkos_dryer_per_m3' => 'decimal:4',
        'hpp_kering_per_m3'       => 'decimal:4',
        'hpp_average'             => 'decimal:4',
        'nilai_stok_akhir'        => 'decimal:4',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    /**
     * Ambil saldo lembar & m3 terakhir SEBELUM tanggal tertentu.
     * Dipakai sebagai stok_awal saat membuat/update log hari ini.
     */
    public static function saldoTerakhir(
        int $idUkuran,
        int $idJenisKayu,
        string $kw,
        string $sebelumTanggal
    ): array {
        $last = static::where('id_ukuran', $idUkuran)
            ->where('id_jenis_kayu', $idJenisKayu)
            ->where('kw', $kw)
            ->whereDate('tanggal', '<', $sebelumTanggal)
            ->orderByDesc('tanggal')
            ->first();

        return [
            'stok_akhir_lembar' => $last ? (int)   $last->stok_akhir_lembar : 0,
            'stok_akhir_m3'     => $last ? (float) $last->stok_akhir_m3     : 0.0,
            'hpp_average'       => $last ? (float) $last->hpp_average        : 0.0,
        ];
    }
}