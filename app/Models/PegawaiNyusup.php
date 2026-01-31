<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiNyusup extends Model
{
    protected $table = 'pegawai_nyusup';

    protected $fillable = [
        'id_produksi_nyusup',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiNyusup()
    {
        return $this->belongsTo(ProduksiNyusup::class, 'id_produksi_nyusup');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function detailPegawaiNyusup()
    {
        return $this->hasMany(DetailBarangDikerjakan::class, 'id_pegawai_nyusup');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_nyusup) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_nyusup, 'nyusup');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_nyusup) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_nyusup, 'nyusup');
            }
        });
    }
}
