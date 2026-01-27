<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class hasil_guellotine extends Model
{
    protected $table = 'hasil_guellotine';

    protected $fillable = [
        'id_produksi_guellotine',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'no_palet',
    ];

    public function produksiGuellotine()
    {
        return $this->belongsTo(produksi_guellotine::class, 'id_produksi_guellotine');
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
