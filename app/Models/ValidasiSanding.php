<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiSanding extends Model
{
    //
    protected $table = 'validasi_sandings'; // ubah jika nama tabel berbeda

    protected $fillable = [
        'id_produksi_sanding',
        'role',
        'status',
    ];

    public function produksiSanding()
    {
        return $this->belongsTo(ProduksiSanding::class, 'id_produksi_sanding');
    }
}
