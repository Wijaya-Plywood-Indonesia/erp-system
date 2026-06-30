<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanHotpress extends Model
{
    protected $table = 'bahan_hotpress';

    protected $fillable = [
        'id_produksi_hp',
        'no_palet',
        'id_barang_setengah_jadi',
        'isi',
        'ket',
    ];

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }

    public function barangSetengahJadi()
    {
        return $this->belongsTo(
            \App\Models\BarangSetengahJadiHp::class,
            'id_barang_setengah_jadi'
        );
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }
}
