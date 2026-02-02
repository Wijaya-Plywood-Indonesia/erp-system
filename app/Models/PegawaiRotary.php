<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiRotary extends Model
{
    protected $table = 'pegawai_rotaries';
    protected $primaryKey = 'id';
    //isian
    protected $fillable = [
        'id_produksi',
        'id_pegawai',
        'role',
        'jam_masuk',
        'jam_pulang',
        'izin',
        'keterangan',
    ];
    public function produksi()
    {
        return $this->belongsTo(ProduksiRotary::class, 'id_produksi');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi, 'rotary');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi, 'rotary');
            }
        });
    }
}
