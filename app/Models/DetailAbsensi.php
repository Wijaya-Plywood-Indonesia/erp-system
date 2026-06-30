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
        'jam_masuk',
        'jam_pulang',
        'tanggal',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function absensi()
    {
        return $this->belongsTo(Absensi::class, 'id_absensi');
    }
}
