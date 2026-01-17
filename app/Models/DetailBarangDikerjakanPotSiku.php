<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailBarangDikerjakanPotSiku extends Model
{
    protected $table = 'detail_barang_dikerjakan_pot_siku';

    protected $fillable = [
        'id_produksi_pot_siku',
        'id_pegawai_pot_siku',
        'id_ukuran',
        'id_jenis_kayu',
        'tinggi',
        'kw',
        'no_palet',
    ];

    public function produksiPotSiku()
    {
        return $this->belongsTo(ProduksiPotSiku::class, 'id_produksi_pot_siku');
    }

    public function PegawaiPotSiku()
    {
        return $this->belongsTo(PegawaiPotSiku::class, 'id_pegawai_pot_siku');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }
}
