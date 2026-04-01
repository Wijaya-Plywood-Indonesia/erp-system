<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProduksiPressDryer extends Model
{
    protected $table = 'produksi_press_dryers';

    protected $fillable = [
        'tanggal_produksi',
        'shift',
        'kendala',
    ];

    protected $casts = [
        'tanggal_produksi' => 'date', // atau 'datetime'
        // casts lainnya...
    ];


    public function detailMasuks()
    {
        return $this->hasMany(DetailMasuk::class, 'id_produksi_dryer');
    }

    public function detailHasils()
    {
        return $this->hasMany(DetailHasil::class, 'id_produksi_dryer');
    }

    public function detailMesins()
    {
        return $this->hasMany(DetailMesin::class, 'id_produksi_dryer');
    }

    public function validasiPressDryers()
    {
        return $this->hasMany(ValidasiPressDryer::class, 'id_produksi_dryer');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiPressDryer::class, 'id_produksi_dryer')->latestOfMany();
    }

    public function detailPegawais()
    {
        return $this->hasMany(DetailPegawai::class, 'id_produksi_dryer');
    }

    public function serahTerima(): HasManyThrough
    {
        return $this->hasManyThrough(
            SerahTerimaPivot::class,
            DetailHasilPaletRotary::class,
            'id_produksi',
            'id_detail_hasil_palet_rotary',
            'id',
            'id'
        );
    }

    public function getLabelAttribute()
    {
        return $this->tanggal_produksi . ' | ' . $this->shift;
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $exists = static::where('tanggal_produksi', $model->tanggal_produksi)
                ->where('shift', $model->shift)
                ->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tanggal_produksi' => 'Data produksi untuk tanggal dan shift ini sudah ada.',
                ]);
            }
        });
    }

    //relasi stok dan log
    public function ongkosDryer()
    {
        return $this->hasOne(OngkosProduksiDryer::class, 'id_produksi_dryer');
    }

    public function stokVeneerKerings()
    {
        return $this->hasMany(StokVeneerKering::class, 'id_produksi_dryer');
    }
}
