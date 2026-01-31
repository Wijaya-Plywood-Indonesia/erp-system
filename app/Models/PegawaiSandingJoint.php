<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiSandingJoint extends Model
{
    protected $table = 'pegawai_sanding_joint';

    protected $fillable = [
        'id_produksi_sanding_joint',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiSandingJoint()
    {
        return $this->belongsTo(ProduksiSandingJoint::class, 'id_produksi_sanding_joint');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_sanding_joint) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_sanding_joint, 'sanding_join');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_sanding_joint) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_sanding_joint, 'sanding_join');
            }
        });
    }
}
