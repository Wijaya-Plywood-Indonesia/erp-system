<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class hasil_guellotine extends Model
{
    protected $table = 'hasil_guellotine';

    protected $fillable = [
        'id_produksi_guellotine',
        'id_pegawai_guellotine',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'no_palet',
    ];

    public function produksiGuellotine()
    {
        return $this->belongsTo(produksi_guellotine::class, 'id_produksi_guellotine');
    }

    public function pegawaiGuellotines()
    {
        return $this->belongsToMany(
            pegawai_guellotine::class,
            'hasil_guellotine_pegawai',
            'id_hasil_guellotine',      // FK ke tabel ini
            'id_pegawai_guellotine'    // FK ke tabel pegawai
        );
    }


    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
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
