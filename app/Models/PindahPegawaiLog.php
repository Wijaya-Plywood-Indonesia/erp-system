<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PindahPegawaiLog extends Model
{
    protected $table = 'pindah_pegawai_logs';

    protected $guarded = [];

    protected $casts = [
        'tanggal' => 'date',
        'dibatalkan_at' => 'datetime',
    ];

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}