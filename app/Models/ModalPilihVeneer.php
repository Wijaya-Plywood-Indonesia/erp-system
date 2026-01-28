<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalPilihVeneer extends Model
{
    protected $table = 'modal_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'kw',
        'no_palet',
    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
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
