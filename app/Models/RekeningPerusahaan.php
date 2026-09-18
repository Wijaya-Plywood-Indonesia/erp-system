<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RekeningPerusahaan extends Model
{
    protected $table = 'rekening_perusahaan';

    protected $primaryKey = 'id';

    public $incrementing = true;

    public $timestamps = true;

    protected $fillable = [
        'pemilik_rekening',
        'nama_bank',
        'no_rekening',
        'atas_nama',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];
}

