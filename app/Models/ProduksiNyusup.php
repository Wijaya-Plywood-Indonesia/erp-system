<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProduksiNyusup extends Model
{
    protected $table = 'produksi_nyusup';

    protected $fillable = [
        'tanggal_produksi',
        'kendala',
    ];

    public function pegawaiNyusup()
    {
        return $this->hasMany(PegawaiNyusup::class, 'id_produksi_nyusup');
    }

    public function detailBarangDikerjakan()
    {
        return $this->hasMany(DetailBarangDikerjakan::class, 'id_produksi_nyusup');
    }

    public function validasiNyusup()
    {
        return $this->hasMany(ValidasiNyusup::class, 'id_produksi_nyusup');
    }

    public function validasiTerakhir()
    {
        return $this->hasOne(ValidasiNyusup::class, 'id_produksi_nyusup')->latestOfMany();
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $exists = static::whereDate('tanggal_produksi', $model->tanggal)->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tanggal_produksi' => 'Data produksi nyusup untuk tanggal ini sudah ada.',
                ]);
            }
        });
    }
}
