<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiPilihPlywood extends Model
{
    protected $table = 'pegawai_pilih_plywood';

    protected $fillable = [
        'id_produksi_pilih_plywood',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiPilihPlywood()
    {
        return $this->belongsTo(ProduksiPilihPlywood::class, 'id_produksi_pilih_plywood');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_pilih_plywood) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pilih_plywood, 'pilih_plywood');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_pilih_plywood) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pilih_plywood, 'pilih_plywood');
            }
        });
    }
}
