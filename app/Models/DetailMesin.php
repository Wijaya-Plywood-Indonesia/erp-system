<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMesin extends Model
{
    protected $table = 'detail_mesin';

    protected $fillable = [
        'id_mesin_dryer',
        'id_kategori_mesin',
        'jam_kerja_mesin',
        'id_produksi_dryer',
    ];

    // RELASI
    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin_dryer');
    }

    public function kategoriMesin()
    {
        return $this->belongsTo(KategoriMesin::class, 'id_kategori_mesin');
    }

    public function produksiDryer()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }
}
