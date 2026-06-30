<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformHasilHp extends Model
{
    protected $table = 'platform_hasil_hp';

    protected $fillable = [
        'id_produksi_hp',
        'id_mesin',
        'no_palet',
        'id_barang_setengah_jadi',
        'isi',
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    public function barangSetengahJadi()
    {
        return $this->belongsTo(
            \App\Models\BarangSetengahJadiHp::class,
            'id_barang_setengah_jadi'
        );
    }

    public function produksiHp()
    {
        return $this->belongsTo(ProduksiHp::class, 'id_produksi_hp');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
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
