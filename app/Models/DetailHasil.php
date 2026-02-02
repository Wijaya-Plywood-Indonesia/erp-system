<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailHasil extends Model
{
    protected $table = 'detail_hasils';

    protected $fillable = [
        'no_palet',
        'kw',
        'isi',
        'id_ukuran',
        'id_jenis_kayu',
        'id_produksi_dryer',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }
    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }
    public function produksi()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_dryer) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_dryer, 'dryer');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_dryer) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_dryer, 'dryer');
            }
        });
    }
}
