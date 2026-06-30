<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailNotaBarangMasuk extends Model
{
    //
    protected $table = 'detail_nota_barang_masuks';

    protected $fillable = [
        'id_nota_bm',
        'nama_barang',
        'jumlah',
        'satuan',
        'keterangan',
    ];

    /**
     * Relasi ke nota induk.
     */
    public function nota()
    {
        return $this->belongsTo(NotaBarangMasuk::class, 'id_nota_bm');
    }
}
