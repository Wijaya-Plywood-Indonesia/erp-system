<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiGrajiBalken extends Model
{
    protected $table = 'pegawai_graji_balken';

    protected $fillable = [
        'id_produksi_graji_balken',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiGrajiBalken()
    {
        return $this->belongsTo(ProduksiGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function hasilGrajiBalken()
    {
        return $this->belongsTo(HasilGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_graji_balken) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_balken, 'graji_balken');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_graji_balken) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_balken, 'graji_balken');
            }
        });
    }
}
