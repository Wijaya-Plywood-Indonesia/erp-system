<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PegawaiTerimaGudangSatu extends Model
{
    protected $table = 'pegawai_terima_gudang_satu';

    protected $fillable = [
        'id_produksi_terima_gudang_satu',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',
    ];

    public function produksiTerimaGudangSatu()
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function pegawai()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            if ($model->id_produksi_terima_gudang_satu) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_terima_gudang_satu, 'terima_gudang_satu');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_terima_gudang_satu) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_terima_gudang_satu, 'terima_gudang_satu');
            }
        });
    }
}
