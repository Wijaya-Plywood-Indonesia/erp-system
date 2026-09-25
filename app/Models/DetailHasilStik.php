<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailHasilStik extends Model
{
    protected $table = 'detail_hasil_stik';

    protected $fillable = [
        'no_palet',
        'kw',
        'total_lembar',
        'id_ukuran',
        'id_jenis_kayu',
        'id_produksi_stik',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiStik::class, 'id_produksi_stik');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }

    // Relasi Multi-Select Pegawai (fitur nyusup: 2 pegawai bisa
    // mengerjakan banyak barang, atau 1 pegawai mengerjakan banyak barang)
    public function pegawais()
    {
        return $this->belongsToMany(
            DetailPegawaiStik::class,
            'detail_hasil_stik_pegawai',
            'detail_hasil_stik_id',
            'detail_pegawai_stik_id'
        )->withTimestamps();
    }
}