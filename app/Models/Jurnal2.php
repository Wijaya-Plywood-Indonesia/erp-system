<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal2 extends Model
{
    protected $table = 'jurnal2';

    protected $fillable = [
        'modif100',
        'no_akun',
        'nama_akun',
        'banyak',
        'kubikasi',
        'harga',
        'total',
        'user_id',
        'status_sinkron',
    ];
}
