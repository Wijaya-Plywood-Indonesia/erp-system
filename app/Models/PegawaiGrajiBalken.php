<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiGrajiBalken extends Model
{
    protected $table = 'pegawai_graji_balken';

    protected $fillable = [
        'id_produksi_graji_balken',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiGrajiBalken()
    {
        return $this->belongsTo(ProduksiGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function hasilGrajiBalken()
    {
        return $this->belongsTo(HasilGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}
