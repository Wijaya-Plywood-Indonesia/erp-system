<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduksiStik extends Model
{
    protected $table = 'produksi_stik';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function detailPegawaiStik()
    {
        return $this->hasMany(DetailPegawaiStik::class, 'id_produksi_stik');
    }

    public function detailMasukStik()
    {
        return $this->hasMany(DetailMasukStik::class, 'id_produksi_stik');
    }

    public function detailHasilStik()
    {
        return $this->hasMany(DetailHasilStik::class, 'id_produksi_stik');
    }

    public function validasiStik()
    {
        return $this->hasMany(ValidasiStik::class, 'id_produksi_stik');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiStik::class, 'id_produksi_stik')->latestOfMany();
    }

    public function serahTerima(): HasMany
    {
        // Dummy — query asli di-override penuh di RelationManager
        return $this->hasMany(SerahTerimaPivot::class, 'id', 'id')
            ->whereRaw('1 = 0');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $exists = static::whereDate('tanggal_produksi', $model->tanggal_produksi)->exists();

            if ($exists) {
                // Melempar pesan error ke UI Filament
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tanggal_produksi' => 'Gagal simpan! Tanggal ini sudah terdaftar di database.',
                ]);
            }
        });
    }
}
