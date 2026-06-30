<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Absensi extends Model
{
    // Inisiasi tanggal
    protected $table = 'absensis';

    protected $fillable = [
        'tanggal',
        'file_path',
        'uploaded_by'
    ];

    protected $casts = [
        'tanggal' => 'date',
        'file_path' => 'array'
    ];

    public function detailAbsensis()
    {
        return $this->hasMany(DetailAbsensi::class, 'id_absensi');
    }
}
