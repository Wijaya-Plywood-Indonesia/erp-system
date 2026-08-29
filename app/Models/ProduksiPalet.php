<?php

namespace App\Models;

use App\Traits\HasRouteUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class ProduksiPalet extends Model
{
    use HasRouteUuid;

    protected $fillable = [
        'uuid',
        'tanggal',
        'keterangan',
    ];

    protected $casts = [
        'tanggal' => 'date',
    ];

    public function pegawaiPalets(): HasMany
    {
        return $this->hasMany(PegawaiPalet::class, 'id_produksi_palet');
    }

    public function hasilProduksiPalets(): HasMany
    {
        return $this->hasMany(HasilProduksiPalet::class, 'id_produksi_palet');
    }

    public function validasiProduksiPalets(): HasMany
    {
        return $this->hasMany(ValidasiProduksiPalet::class, 'id_produksi_palet');
    }

    protected static function booted(): void
    {
        static::creating(function ($model) {
            // Cek apakah laporan produksi palet untuk tanggal yang sama sudah dibuat
            $exists = static::where('tanggal', $model->tanggal)->exists();

            if ($exists) {
                throw ValidationException::withMessages([
                    'tanggal' => 'Laporan produksi palet untuk tanggal tersebut sudah ada.',
                ]);
            }
        });
    }
}
