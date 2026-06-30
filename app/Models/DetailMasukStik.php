<?php

namespace App\Models;

use App\Casts\NomorPaletCast;
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

    // ✅ Pasang Cast yang sama dengan DetailMasuk
    protected $casts = [
        'no_palet' => NomorPaletCast::class,
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

    // ✅ Relasi ke DetailHasilPaletRotary (sama dengan DetailMasuk)
    public function detailPaletRotary()
    {
        return $this->belongsTo(
            DetailHasilPaletRotary::class,
            'no_palet',
            'id'
        );
    }

    public function getIsAfAttribute(): bool
    {
        return $this->getRawOriginal('no_palet') <= 0;
    }

    // ✅ nextAfNumber untuk tabel stik (terpisah dari dryer)
    public static function nextAfNumber(): int
    {
        $min = static::where('no_palet', '<', 0)->min('no_palet');
        return $min ? $min - 1 : -1;
    }

    protected static function booted()
    {
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
