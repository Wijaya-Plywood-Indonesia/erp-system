<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class JabatanPerusahaan extends Model
{
    protected $table = 'jabatan_perusahaan';

    protected $fillable = [
        'perusahaan_id',
        'nama_jabatan',
        'deskripsi',
        'jam_masuk',
        'jam_pulang',
        'istirahat_mulai',
        'istirahat_selesai',
    ];

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class);
    }

    public function pegawais()
    {
        return $this->hasMany(Pegawai::class, 'jabatan_id');
    }
}