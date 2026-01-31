<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailDempul extends Model
{
    protected $table = 'detail_dempuls';

    protected $fillable = [
        'id_produksi_dempul',
        'id_barang_setengah_jadi_hp',
        'modal',
        'hasil',
        'nomor_palet',
        'jam_masuk',
        'jam_pulang',
        'ijin',
        'keterangan'
    ];

    public function produksiDempul(): BelongsTo
    {
        return $this->belongsTo(ProduksiDempul::class, 'id_produksi_dempul');
    }

    public function rencanaPegawaiDempul(): BelongsTo
    {
        return $this->belongsTo(RencanaPegawaiDempul::class, 'id_rencana_pegawai_dempul');
    }

    public function barangSetengahJadi(): BelongsTo
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function pegawais()
    {
        return $this->belongsToMany(
            Pegawai::class,
            'detail_dempul_pegawai',
            'id_detail_dempul',
            'id_pegawai'
        );
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_dempul) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_dempul, 'dempul');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_dempul) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_dempul, 'dempul');
            }
        });
    }
}
