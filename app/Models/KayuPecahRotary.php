<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Spatie\Image\Image;
use Illuminate\Support\Facades\Log;

class KayuPecahRotary extends Model
{
    //

    protected $table = 'kayu_pecah_rotaries';
    protected $primaryKey = 'id';
    protected $fillable = [
        'id_produksi',
        'id_penggunaan_lahan',
        'ukuran',
        'foto',
    ];
    public function penggunaanLahan()
    {
        return $this->belongsTo(PenggunaanLahanRotary::class, 'id_penggunaan_lahan');
    }
    public function produksi_rotary()
    {
        return $this->belongsTo(ProduksiRotary::class, 'id_produksi');
    }
    protected static function booted(): void
    {
        static::saved(function ($model) {
            if ($model->foto && Storage::disk('public')->exists($model->foto)) {
                $path = Storage::disk('public')->path($model->foto);

                try {
                    Image::load($path)
                        ->width(1280)
                        ->height(1280)
                        ->quality(80)
                        ->optimize()
                        ->save();
                } catch (\Throwable $th) {
                    Log::warning("Gagal kompres foto kayu pecah: {$th->getMessage()}");
                }
            }
        });
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

}
