<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Neraca extends Model
{
    // Inisiasi Table
    protected $table = 'neracas';

    protected $fillable = [
        'akun_seribu',
        'detail',
        'banyak',
        'kubikasi',
        'harga',
        'total'
    ];
}
