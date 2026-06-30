<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ValidasiGrajiStik extends Model
{
    //
    protected $table = 'validasi_graji_stiks';

    protected $fillable = [
        'id_graji_stiks',
        'role',
        'status'
    ];

    public function grajiStik()
    {
        return $this->belongsTo(GrajiStik::class, 'id_graji_stiks');
    }
}
