<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPotJelek extends Model
{
    protected $table = 'pegawai_pot_jelek';

    protected $fillable = [
        'id_produksi_pot_jelek',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'tinggi',
        'ijin',
        'ket',
    ];

    public function produksiPotJelek()
    {
        return $this->belongsTo(ProduksiPotJelek::class, 'id_produksi_pot_jelek');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_pot_jelek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_jelek, 'pot_jelek');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_pot_jelek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_jelek, 'pot_jelek');
            }
        });
    }
}
