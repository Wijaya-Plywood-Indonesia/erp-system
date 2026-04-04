<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Casts\Attribute;

class TempatKayu extends Model
{
    //

    protected $table = 'tempat_kayus';
    protected $primaryKey = 'id';

    protected $with = ['lahan', 'kayuMasuk.detailMasukanKayu'];

    protected $fillable = [
        'jumlah_batang',
        'poin',
        'id_kayu_masuk',
        'id_lahan',
    ];

    public function kayuMasuk(): BelongsTo
    {
        return $this->belongsTo(KayuMasuk::class, 'id_kayu_masuk');
    }

    public function riwayatKayu(): HasMany
    {
        return $this->hasMany(RiwayatKayu::class, 'id_tempat_kayu');
    }

    public function lahan(): BelongsTo
    {
        return $this->belongsTo(Lahan::class, 'id_lahan');
    }

    // public function turunKayu(): BelongsTo
    // {
    //     return $this->belongsTo(TurunKayu::class, 'id_turun_kayu');
    // }

    // Detail kayu
    protected function detailKayu(): Attribute
    {
        return Attribute::make(
            get: fn() => $this->kayuMasuk?->detailMasukanKayu->first()
        );
    }

    protected function diameter(): Attribute
    {
        // Ambil diameter dari helper 'detailKayu'
        return Attribute::make(
            get: fn() => $this->detailKayu?->diameter ?? 0
        );
    }

    protected function kubikasi(): Attribute
    {
        return Attribute::make(
            get: function () {
                $stok = \App\Models\HppAverageSummarie::where('id_lahan', $this->id_lahan)
                    ->first();

                return $stok?->stok_kubikasi ?? 0;
            }
        );
    }

    protected function selectLabel(): Attribute
    {
        return Attribute::make(
            get: function () {
                $kode_lahan = $this->lahan?->kode_lahan ?? '[Tanpa Lahan]';

                $kubikasi = $this->kubikasi; // Ini akan memanggil kubikasi()

                // Format label yang akan muncul di tabel dan dropdown
                return "{$kode_lahan} | {$this->jumlah_batang} btg | {$kubikasi} cm³";
            }
        );
    }
}
