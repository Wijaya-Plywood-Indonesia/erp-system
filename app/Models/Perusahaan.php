<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Perusahaan extends Model
{
    //
    protected $table = 'perusahaan';

    protected $fillable = [
        'kode',
        'nama',
        'alamat',
        'telepon',
        'email',
    ];

    public function jabatans()
    {
        return $this->hasMany(JabatanPerusahaan::class, 'perusahaan_id');
    }



    public function pegawais()
    {
        return $this->hasMany(Pegawai::class, 'perusahaan_id');
    }
}
