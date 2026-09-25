<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPegawaiStik extends Model
{
    protected $table = 'detail_pegawai_stik';

    protected $fillable = [
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
        'id_produksi_stik',
        'id_pegawai',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiStik::class, 'id_produksi_stik');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function detailHasilStik()
    {
        return $this->belongsToMany(
            DetailHasilStik::class,
            'detail_hasil_stik_pegawai',
            'detail_pegawai_stik_id',
            'detail_hasil_stik_id'
        )->withTimestamps();
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_stik) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_stik, 'stik');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_stik) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_stik, 'stik');
            }
        });
    }
}