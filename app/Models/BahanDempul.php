<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanDempul extends Model
{
    protected $table = 'bahan_dempuls';

    protected $fillable = [
        'id_produksi_dempul',
        'nama_bahan',
        'jumlah',
    ];

    public function produksiDempul()
    {
        return $this->belongsTo(ProduksiDempul::class, 'id_produksi_dempul');
    }

    public function serahTerimaTriplekCacat(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaTriplekCacat::class, 'id_serah_terima_triplek_cacat');
    }
}
