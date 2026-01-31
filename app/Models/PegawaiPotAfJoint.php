<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPotAfJoint extends Model
{
    protected $table = 'pegawai_pot_af_joint';

    protected $fillable = [
        'id_produksi_pot_af_joint',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiPotAfJoint()
    {
        return $this->belongsTo(ProduksiPotAfJoint::class, 'id_produksi_pot_af_joint');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_pot_af_joint) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_af_joint, 'pot_af');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_pot_af_joint) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_af_joint, 'pot_af');
            }
        });
    }
}
