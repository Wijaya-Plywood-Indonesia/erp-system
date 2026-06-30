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
}
