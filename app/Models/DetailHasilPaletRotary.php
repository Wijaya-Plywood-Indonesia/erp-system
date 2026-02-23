<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailHasilPaletRotary extends Model
{
    protected $table = 'detail_hasil_palet_rotaries';
    protected $primaryKey = 'id';
    //
    protected $fillable = [

        'id_produksi',
        'id_penggunaan_lahan',
        'produksi_rotaries',
        'timestamp_laporan',
        'id_ukuran',
        'kw',
        'palet',
        'total_lembar',
    ];
    //
    public function produksi()
    {
        return $this->belongsTo(ProduksiRotary::class, 'id_produksi');
    }

    public function setoranPaletUkuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }
    
    public function penggunaanLahan()
    {
        return $this->belongsTo(PenggunaanLahanRotary::class, 'id_penggunaan_lahan', 'id');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function lahan()
    {
        return $this->hasOneThrough(
            Lahan::class,
            PenggunaanLahanRotary::class,
            'id', // foreign key di tabel perantara
            'id',           // foreign key di tabel lahan
            'id',                 // primary key di produksi
            'id_lahan'            // local key di penggunaan_lahan_rotary
        );
    }
    public function getGroupLahanAttribute()
    {
        $lahan = $this->penggunaanLahan?->lahan;
        return $lahan ? "{$lahan->kode_lahan} - {$lahan->nama_lahan}" : '-';
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi, 'rotary');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi, 'rotary');
            }
        });
    }
}
