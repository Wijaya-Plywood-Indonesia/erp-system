<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPilihVeneer extends Model
{
    protected $table = 'pegawai_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',

    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        /**
         * static::saved mencakup event Created (data baru) 
         * dan Updated (perubahan data lama).
         */
        static::saved(function ($model) {
            if ($model->id_produksi_pilih_veneer) {
                \App\Events\ProductionUpdated::dispatch(
                    $model->id_produksi_pilih_veneer,
                    'veneer'
                );
            }
        });

        /**
         * static::deleted memastikan widget refresh 
         * saat ada data yang dihapus.
         */
        static::deleted(function ($model) {
            if ($model->id_produksi_pilih_veneer) {
                \App\Events\ProductionUpdated::dispatch(
                    $model->id_produksi_pilih_veneer,
                    'veneer'
                );
            }
        });
    }
}
