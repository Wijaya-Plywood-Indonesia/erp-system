<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TurunKayu extends Model
{
    //

    protected $table = 'turun_kayus';
    protected $primaryKey = 'id';

    // Sesuaikan fillable dengan ERD Anda, bukan hanya id dan tanggal
    protected $fillable = [
        'tanggal',
        'kendala'
    ];
    protected $casts = [
        'tanggal' => 'datetime',
    ];

    public function detailTurunKayu()
    {
        return $this->hasMany(DetailTurunKayu::class, 'id_turun_kayu');
    }
    // public function tempatKayu(): HasMany
    // {
    //     return $this->hasMany(TempatKayu::class, 'id_turun_kayu');
    // }
    public function pegawaiTurunKayu()
    {
        return $this->hasMany(PegawaiTurunKayu::class, 'id_turun_kayu');
    }
}
