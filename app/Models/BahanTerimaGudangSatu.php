<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BahanTerimaGudangSatu extends Model
{
    protected $table = 'bahan_terima_gudang_satu';

    protected $fillable = [
        'id_produksi_terima_gudang_satu',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'highlight_bahan',
        'jumlah',
    ];

    public function produksiTerimaGudangSatu()
    {
        return $this->belongsTo(ProduksiTerimaGudangSatu::class, 'id_produksi_terima_gudang_satu');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
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
