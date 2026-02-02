<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPotSiku extends Model
{
    protected $table = 'pegawai_pot_siku';

    protected $fillable = [
        'id_produksi_pot_siku',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'tinggi',
        'ijin',
        'ket',
    ];

    public function produksiPotSiku()
    {
        return $this->belongsTo(ProduksiPotSiku::class, 'id_produksi_pot_siku');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_pot_siku) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_siku, 'pot_siku');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_pot_siku) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_siku, 'pot_siku');
            }
        });
    }
}
