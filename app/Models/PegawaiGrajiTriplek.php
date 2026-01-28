<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiGrajiTriplek extends Model
{
    protected $table = 'pegawai_graji_triplek';

    protected $fillable = [
        'id_produksi_graji_triplek',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',

    ];

    public function produksiGrajiTriplek()
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'id_produksi_graji_triplek');
    }

    public function pegawaiGrajiTriplek()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}
