<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class validasi_guellotine extends Model
{
    protected $table = 'validasi_guellotine';

    protected $fillable = [
        'id_produksi_guellotine',
        'role',
        'status',
    ];

    public function produksiGuellotine()
    {
        return $this->belongsTo(produksi_guellotine::class, 'id_produksi_guellotine');
    }
}
