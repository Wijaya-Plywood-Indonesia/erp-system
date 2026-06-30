<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailNotaBarangKeluar extends Model
{
    //
    protected $table = 'detail_nota_barang_keluar';

    protected $fillable = [
        'id_nota_bk',
        'nama_barang',
        'jumlah',
        'satuan',
        'keterangan',
    ];

    // Relasi ke Nota Barang Keluar
    public function nota()
    {
        return $this->belongsTo(NotaBarangKeluar::class, 'id_nota_bk');
    }
}
