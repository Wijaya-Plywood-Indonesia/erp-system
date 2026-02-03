<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JurnalTiga extends Model
{
    // Inisiasi Table
    protected $table = 'jurnal_tigas';


    protected $fillable = [
        'modif1000',
        'akun_seratus',
        'detail',
        'banyak',
        'kubikasi',
        'harga',
        'total',
        'createdBy',
        'status'
    ];

    protected $casts = [
        'akun_seratus' => 'integer'
    ];
}
