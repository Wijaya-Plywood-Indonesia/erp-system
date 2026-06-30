<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiSanding extends Model
{
    //

    protected $fillable = [
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
        'id_produksi_sanding',
        'id_pegawai',
    ];

    protected $casts = [
        'masuk' => 'datetime:H:i',
        'pulang' => 'datetime:H:i',
    ];

    /**
     * Relasi ke Produksi Sanding
     */
    public function produksiSanding()
    {
        return $this->belongsTo(ProduksiSanding::class, 'id_produksi_sanding');
    }

    /**
     * Relasi ke Pegawai
     */
    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_sanding) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_sanding, 'sanding');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_sanding) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_sanding, 'sanding');
            }
        });
    }
}
