<?php

namespace App\Models;

use App\Events\ProductionUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class DetailBongkarKedi extends Model
{
    protected $table = 'detail_bongkar_kedi';

    protected $fillable = [
        'no_palet',
        'kode_kedi',
        'id_jenis_kayu',
        'id_ukuran',
        'kw',
        'jumlah',
        'id_produksi_kedi',
    ];

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
                ProductionUpdated::dispatch($model->id_produksi_kedi, 'kedi');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_kedi) {
                ProductionUpdated::dispatch($model->id_produksi_kedi, 'kedi');
            }
        });
    }

    // di App\Models\DetailBongkarKedi
    public function serahTerimaVeneerKering(): HasOne
    {
        return $this->hasOne(SerahTerimaVeneerKering::class, 'id_detail_bongkar_kedi');
    }
}
