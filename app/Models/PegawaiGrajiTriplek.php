<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiGrajiTriplek extends Model
{
    protected $table = 'pegawai_graji_triplek';

    protected $fillable = [
        'id_produksi_graji_triplek',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',

    ];

    public function produksiGrajiTriplek()
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'id_produksi_graji_triplek');
    }

    public function pegawaiGrajiTriplek()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_graji_triplek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_triplek, 'graji_triplek');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_graji_triplek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_triplek, 'graji_triplek');
            }
        });
    }
}
