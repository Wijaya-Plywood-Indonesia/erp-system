<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMasuk extends Model
{
    protected $table = 'detail_masuks';

    protected $fillable = [
        'no_palet',
        'kw',
        'isi',
        'id_ukuran',
        'id_jenis_kayu',
        'id_produksi_dryer',
    ];


    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }
    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }
    public function produksiDryer()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }
}
