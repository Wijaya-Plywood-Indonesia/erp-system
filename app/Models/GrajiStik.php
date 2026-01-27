<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GrajiStik extends Model
{
    protected $table = 'graji_stiks';

    protected $fillable = [
        'tanggal',
        'kendala',
    ];

    public function modalGrajiStik()
    {
        return $this->hasMany(ModalGrajiStik::class, 'id_graji_stiks');
    }

    public function pegawaiGrajiStik()
    {
        return $this->hasMany(PegawaiGrajiStik::class, 'id_graji_stiks');
    }

    public function hasilGrajiStik()
    {
        return $this->hasMany(HasilGrajiStik::class, 'id_graji_stiks');
    }

    public function validasiGrajiStik()
    {
        return $this->hasMany(ValidasiGrajiStik::class, 'id_graji_stiks');
    }

    public function isLocked(): bool
    {
        // Cek apakah status terakhir adalah 'divalidasi'
        return $this->validasiTerakhir?->status === 'divalidasi';
    }
}
