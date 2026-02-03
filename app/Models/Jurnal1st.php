<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal1st extends Model
{
    //
    protected $table = 'jurnal_1st';

    protected $fillable = [
        'modif10',
        'no_akun',
        'nama_akun',
        'bagian',
        'banyak',
        'm3',
        'harga',
        'tot',
        'created_by',
    ];

    protected $casts = [
        'modif10' => 'integer',
        'no_akun' => 'integer',
        'banyak' => 'integer',
        'm3' => 'decimal:4',
        'harga' => 'integer',
        'tot' => 'integer',
        'created_by' => 'string',
    ];
}
