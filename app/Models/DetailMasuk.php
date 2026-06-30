<?php

namespace App\Models;

use App\Casts\NomorPaletCast;
use Illuminate\Database\Eloquent\Model;

class DetailMasuk extends Model
{
    protected $table = 'detail_masuks';

    protected $fillable = [
        'no_palet',
        'kw',
        'isi',
        'id_ukuran',
        'id_jenis_kayu',
        'id_produksi_dryer',
    ];

    protected $casts = [
        'no_palet' => NomorPaletCast::class,
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu', 'id');
    }

    public function produksiDryer()
    {
        return $this->belongsTo(ProduksiPressDryer::class, 'id_produksi_dryer');
    }

    // ✅ FIX: foreign key harus 'no_palet' bukan 'palet'
    public function detailPaletRotary()
    {
        return $this->belongsTo(
            DetailHasilPaletRotary::class,
            'no_palet', // foreign key di detail_masuks
            'id'        // primary key di detail_hasil_palet_rotaries
        );
    }

    public function getIsAfAttribute(): bool
    {
        return $this->getRawOriginal('no_palet') <= 0;
    }

    public static function nextAfNumber(): int
    {
        $min = static::where('no_palet', '<', 0)->min('no_palet');
        return $min ? $min - 1 : -1;
    }
}
