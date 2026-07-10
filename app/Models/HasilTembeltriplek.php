<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HasilTembeltriplek extends Model
{
    protected $table = 'hasil_tembel_triplek';

    protected $fillable = [
        'id_produksi_tembel_triplek',
        'id_pegawai_tembel_triplek', 
        'id_barang_setengah_jadi_hp',
        'modal',
        'hasil',
        'nomor_palet',
    ];

    public function produksiTembeltriplek(): BelongsTo
    {
        return $this->belongsTo(ProduksiTembeltriplek::class, 'id_produksi_tembel_triplek');
    }

    public function pegawaiTembeltriplek(): BelongsTo
    {
        return $this->belongsTo(PegawaiTembeltriplek::class, 'id_pegawai_tembel_triplek');
    }

    public function barangSetengahJadi(): BelongsTo
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function pegawais()
    {
        return $this->belongsToMany(
            Pegawai::class,
            'hasil_tembel_triplek_pegawai',
            'id_hasil_tembel_triplek',
            'id_pegawai'
        );
    }

    protected static function booted()
    {
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