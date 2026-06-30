<?php

// app/Models/PegawaiTurunKayu.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiTurunKayu extends Model
{
    protected $table = 'pegawai_turun_kayus';

    protected $fillable = [
        'id_turun_kayu',
        'id_pegawai',
        'jam_masuk',
        'jam_pulang',
        'izin',
        'ket',
    ];


    // Relasi ke TurunKayu
    public function turunKayu(): BelongsTo
    {
        return $this->belongsTo(TurunKayu::class, 'id_turun_kayu');
    }

    // Relasi ke Pegawai
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }
}