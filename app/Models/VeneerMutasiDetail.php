<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerMutasiDetail extends Model
{
    protected $table = 'veneer_mutasi_details';

    protected $fillable = [
        'id_veneer_mutasi',
        'tipe_veneer',
        'id_ukuran',
        'id_jenis_kayu',
        'kw',
        'qty',
        'm3',
    ];

    public function mutasi()
    {
        return $this->belongsTo(VeneerMutasi::class, 'id_veneer_mutasi');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function stokVeneerKering()
    {
        return $this->hasOne(StokVeneerKering::class, 'id_veneer_mutasi_detail');
    }
}
