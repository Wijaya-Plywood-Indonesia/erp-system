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
        'tanggal' => 'date',
        'total_m3_masuk' => 'decimal:6',
        'total_m3_keluar' => 'decimal:6',
        'stok_akhir_m3' => 'decimal:6',
        'hpp_veneer_basah_per_m3' => 'decimal:4',
        'avg_ongkos_dryer_per_m3' => 'decimal:4',
        'hpp_kering_per_m3' => 'decimal:4',
        'hpp_average' => 'decimal:4',
        'nilai_stok_akhir' => 'decimal:4',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }
}