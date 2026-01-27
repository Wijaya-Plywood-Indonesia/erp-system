<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class pegawai_guellotine extends Model
{
    protected $table = 'pegawai_guellotine';

    protected $fillable = [
        'id_produksi_guellotine',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiGuellotine()
    {
        return $this->belongsTo(produksi_guellotine::class, 'id_produksi_guellotine');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}
