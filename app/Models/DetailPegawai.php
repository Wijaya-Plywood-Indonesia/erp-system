<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPegawai extends Model
{
    protected $table = 'detail_pegawais';

    protected $fillable = [
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
        'id_produksi_dryer',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    public function pegawai()
    {
        return $this->belongsTo(\App\Models\Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_dryer) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_dryer, 'dryer');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_dryer) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_dryer, 'dryer');
            }
        });
    }
}
