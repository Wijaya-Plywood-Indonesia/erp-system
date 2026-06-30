<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class pegawai_guellotine extends Model
{
    protected $table = 'pegawai_guellotine';

    protected $fillable = [
        'id_produksi_guellotine',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiGuellotine()
    {
        return $this->belongsTo(produksi_guellotine::class, 'id_produksi_guellotine');
    }

    public function hasilGuellotines()
    {
        return $this->belongsToMany(
            hasil_guellotine::class,
            'hasil_guellotine_pegawai',
            'id_pegawai_guellotine',
            'id_hasil_guellotine'
        );
    }


    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_guellotine) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_guellotine, 'guellotine');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_guellotine) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_guellotine, 'guellotine');
            }
        });
    }
}
