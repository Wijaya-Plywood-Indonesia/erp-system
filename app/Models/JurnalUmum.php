<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalUmum extends Model
{
    //
    /**
     * Karena nama tabel tidak mengikuti konvensi Laravel (harusnya jurnal_umums),
     * maka wajib didefinisikan manual.
     */
    protected $table = 'jurnal_umum';

    /**
     * Kolom yang boleh diisi mass-assignment.
     */
    protected $fillable = [
        'tgl',
        'jurnal',
        'no_akun',
        'no-dokumen',
        'mm',
        'nama',
        'keterangan',
        'map',
        'hit_kbk',
        'banyak',
        'm3',
        'harga',
        'created_by',
        'status',
    ];

    /**
     * Casting untuk tipe data.
     */
    protected $casts = [
        'tgl' => 'date',
        'jurnal' => 'integer',
        'no_akun' => 'integer',
        'mm' => 'integer',
        'banyak' => 'integer',
        'm3' => 'decimal:4',
        'harga' => 'integer',
    ];


}
