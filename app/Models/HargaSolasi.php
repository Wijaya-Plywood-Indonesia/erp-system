<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HargaSolasi extends Model
{
    // Table Init
    protected $table = 'harga_solasi';

    protected $fillable = [
        'harga'
    ];
}
