<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailAbsensi extends Model
{
    // Inisiasi Table 
    protected $table = 'detail_absensis';

    protected $fillable = [
        'id_absensi',
        'kode_pegawai',
        'nama_pegawai',
        'jam_masuk',
        'jam_pulang',
        'tanggal',
        'id_pegawai',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function absensi()
    {
        return $this->belongsTo(Absensi::class, 'id_absensi');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}
