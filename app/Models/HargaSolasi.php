<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HargaSolasi extends Model
{
    // Table Init
    protected $table = 'harga_solasis';

    protected $fillable = [
        'harga'
    ];
}
