<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProduksiRepair extends Model
{
    protected $table = 'produksi_repairs';

    protected $fillable = [
        'tanggal',
        'kendala'
    ];


    public function rencanaPegawais(): HasMany
    {
        return $this->hasMany(RencanaPegawai::class, 'id_produksi_repair');
    }

    public function rencanaRepair(): HasMany
    {
        return $this->hasMany(RencanaRepair::class, 'id_produksi_repair');
    }

    public function hasilRepairs(): HasMany
    {
        return $this->hasMany(HasilRepair::class, 'id_produksi_repair');
    }

    public function modalRepairs(): HasMany
    {
        return $this->hasMany(ModalRepair::class, 'id_produksi_repair');
    }

    public function validasiRepairs(): HasMany
    {
        return $this->hasMany(ValidasiRepair::class, 'id_produksi_repair');
    }

    protected static function booted()
    {
        static::creating(function ($model) {
            $exists = static::whereDate('tanggal', $model->tanggal)->exists();

            if ($exists) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'tanggal' => 'Data produksi repair untuk tanggal ini sudah ada.',
                ]);
            }
        });
    }
}
