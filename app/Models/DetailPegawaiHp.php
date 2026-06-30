<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailPegawaiHp extends Model
{
    protected $table = 'detail_pegawai_hp';

    protected $fillable = [
        'id_produksi_hp',
        'id_mesin',
        'id_pegawai',
        'tugas',
        'masuk',
        'pulang',
        'ijin',
        'ket',

    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }

    public function pegawaiHp()
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_hp) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_hp, 'hotpress');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_hp) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_hp, 'hotpress');
            }
        });
    }
}
