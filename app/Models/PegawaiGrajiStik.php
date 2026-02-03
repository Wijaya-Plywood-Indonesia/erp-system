<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiGrajiStik extends Model
{
    protected $table = 'pegawai_graji_stiks';

    protected $fillable = [
        'id_graji_stiks',
        'id_pegawai',
        'jam_masuk',
        'jam_pulang',
        'ijin',
        'keterangan'
    ];

    public function grajiStik()
    {
        return $this->belongsTo(GrajiStik::class, 'id_graji_stiks');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_graji_stiks) {
                \App\Events\ProductionUpdated::dispatch($model->id_graji_stiks, 'graji_stik');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_graji_stiks) {
                \App\Events\ProductionUpdated::dispatch($model->id_graji_stiks, 'graji_stik');
            }
        });
    }
}
