<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilSandingPutaran extends Model
{
    protected $fillable = [
        'hasil_sanding_id',
        'sisi',
        'putaran_ke',
        'modal',
        'hasil',
        'selisih',
        'status_cek',
        'catatan',
        'machine_id',
        'waktu_mesin',
    ];
}
