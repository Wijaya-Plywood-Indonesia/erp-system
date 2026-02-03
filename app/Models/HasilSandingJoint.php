<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilSandingJoint extends Model
{
    protected $table = 'hasil_sanding_joint';

    protected $fillable = [
        'id_produksi_sanding_joint',
        'id_ukuran',
        'id_jenis_kayu',
        'no_palet',
        'kw',
        'jumlah',
    ];

    public function produksiSandingJoint()
    {
        return $this->belongsTo(ProduksiSandingJoint::class, 'id_produksi_sanding_joint');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
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
