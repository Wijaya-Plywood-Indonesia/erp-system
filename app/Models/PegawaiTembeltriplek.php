<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PegawaiTembeltriplek extends Model
{
    protected $table = 'pegawai_tembel_triplek';

    protected $fillable = [
        'id_produksi_tembel_triplek',
        'id_pegawai',
        'jam_masuk',
        'jam_pulang',
        'ijin',
        'keterangan',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function produksiTembeltriplek(): BelongsTo
    {
        return $this->belongsTo(ProduksiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }


    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_tembel_triplek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_tembel_triplek, 'tembel_triplek');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_tembel_triplek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_tembel_triplek, 'tembel_triplek');
            }
        });
    }
}
