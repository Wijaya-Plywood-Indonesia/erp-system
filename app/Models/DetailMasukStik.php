<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailMasukStik extends Model
{
    protected $table = 'detail_masuk_stik';

    protected $fillable = [
        'no_palet',
        'kw',
        'isi',
        'id_ukuran',
        'id_jenis_kayu',
        'id_produksi_stik',
    ];

    public function produksi()
    {
        return $this->belongsTo(ProduksiStik::class, 'id_produksi_stik');
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
            if ($model->id_produksi_stik) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_stik, 'stik');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_stik) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_stik, 'stik');
            }
        });
    }
}
