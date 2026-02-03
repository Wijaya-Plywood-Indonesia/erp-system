<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiJoint extends Model
{
    protected $table = 'pegawai_joint';

    protected $fillable = [
        'id_produksi_joint',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiJoint()
    {
        return $this->belongsTo(ProduksiJoint::class, 'id_produksi_joint');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_joint) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_joint, 'join');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_joint) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_joint, 'join');
            }
        });
    }
}
