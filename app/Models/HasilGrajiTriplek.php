<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilGrajiTriplek extends Model
{
    protected $table = 'hasil_graji_triplek';

    protected $fillable = [
        'id_produksi_graji_triplek',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'isi',
    ];

    public function produksiGrajiTriplek()
    {
        return $this->belongsTo(ProduksiGrajitriplek::class, 'id_produksi_graji_triplek');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_graji_triplek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_triplek, 'graji_triplek');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_graji_triplek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_triplek, 'graji_triplek');
            }
        });
    }
}
