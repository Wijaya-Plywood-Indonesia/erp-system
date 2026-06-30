<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Jurnal1st extends Model
{
    //
    protected $table = 'jurnal_1st';

    protected $guarded = [];

    protected $fillable = [
        'modif10',
        'no_akun',
        'nama_akun',
        'bagian',
        'banyak',
        'm3',
        'harga',
        'total',
        'created_by',
        'status',
        'synced_at',
        'synced_by',
    ];

    protected $casts = [
        'modif10' => 'integer',
        'no_akun' => 'string',
        'banyak' => 'integer',
        'm3' => 'decimal:4',
        'harga' => 'decimal:2',
'total' => 'decimal:2',
        'created_by' => 'string',
    ];
}
