<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPegawaiKedi extends Model
{
    protected $table = 'detail_pegawai_kedi';

    protected $fillable = [
        'id_produksi_kedi',
        'id_mesin',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',

    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    public function produksiKedi()
    {
        return $this->belongsTo(ProduksiKedi::class, 'id_produksi_kedi');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_kedi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_kedi, 'kedi');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_kedi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_kedi, 'kedi');
            }
        });
    }
}
