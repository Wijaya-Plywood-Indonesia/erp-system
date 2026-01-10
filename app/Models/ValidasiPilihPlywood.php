<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiPilihPlywood extends Model
{
    protected $table = 'validasi_pilih_plywood';

    protected $fillable = [
        'id_hasil_pilih_plywood',
        'role',
        'status',
    ];

    public function produksiPilihPlywood()
    {
        return $this->belongsTo(ProduksiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }
}
