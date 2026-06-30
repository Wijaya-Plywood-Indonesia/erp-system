<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilSanding extends Model
{
    //
    protected $table = 'hasil_sandings';

    protected $fillable = [
        'id_produksi_sanding',
        'id_barang_setengah_jadi',
        'kuantitas',
        'jumlah_sanding_face',
        'jumlah_sanding_back',
        'id_mesin',
        'no_palet',
        'status',
    ];

    public function produksiSanding()
    {
        return $this->belongsTo(ProduksiSanding::class, 'id_produksi_sanding');
    }

    public function barangSetengahJadi()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi');
    }
    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_sanding) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_sanding, 'sanding');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_sanding) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_sanding, 'sanding');
            }
        });
    }
}
