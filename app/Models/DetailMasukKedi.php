<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMasukKedi extends Model
{
    protected $table = 'detail_masuk_kedi';

    protected $fillable = [
        'id_mesin',
        'no_palet',
        'kode_kedi',
        'id_ukuran',
        'id_jenis_kayu',
        'kw',
        'jumlah',
        'rencana_bongkar',
        'id_produksi_kedi',
    ];

    public function mesin()
    {
        return $this->belongsTo(Mesin::class, 'id_mesin');
    }

    public function produksi()
    {
        return $this->belongsTo(ProduksiKedi::class, 'id_produksi_kedi');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_kedi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_kedi, 'kedi');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_kedi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_kedi, 'kedi');
            }
        });
    }
}
