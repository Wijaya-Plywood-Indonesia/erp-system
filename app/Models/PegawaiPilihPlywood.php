<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPilihPlywood extends Model
{
    protected $table = 'pegawai_pilih_plywood';

    protected $fillable = [
        'id_produksi_pilih_plywood',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiPilihPlywood()
    {
        return $this->belongsTo(ProduksiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}
