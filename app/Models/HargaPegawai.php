<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HargaPegawai extends Model
{
    // table init
    protected $table = 'harga_pegawais';

    protected $fillable = [
        'harga'
    ];
}
