<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Pegawai extends Model
{
    protected $table = 'pegawais';
    protected $primaryKey = 'id';
    // Kolom yang boleh diisi mass-assignment
    protected $fillable = [
        'kode_pegawai',
        'nama_pegawai',
        'panggilan',
        'alamat',
        'no_telepon_pegawai',
        'jenis_kelamin_pegawai',
        'tanggal_masuk',
        'foto',

        // Kolom baru
        'karyawan_di',
        'alamat_perusahaan',
        'jabatan',
        'nik',
        'tempat_tanggal_lahir',
        'scan_ktp',
        'scan_kk',
    ];
    public function pegawaiRotaries()
    {
        return $this->hasMany(PegawaiRotary::class, 'id_pegawai');
    }

    public function detailTurunKayu()
    {
        return $this->hasMany(DetailTurunKayu::class, 'id_pegawai');
    }

    public function detailPegawai()
    {
        return $this->hasMany(DetailPegawai::class, 'id_pegawai');
    }

    public function detailPegawaiStik()
    {
        return $this->hasMany(DetailPegawaiStik::class, 'id_pegawai');
    }

    public function detailPegawaiHp()
    {
        return $this->hasMany(DetailPegawaiHp::class, 'id_pegawai');
    }

    public function detailPegawaiKedi()
    {
        return $this->hasMany(DetailPegawaiKedi::class, 'id_pegawai');
    }

    public function rencanaPegawais()
    {
        return $this->hasMany(RencanaPegawai::class, 'id_pegawai');
    }

    public function detailPegawaiGrajiTriplek()
    {
        return $this->hasMany(PegawaiGrajiTriplek::class, 'id_pegawai');
    }

    public function lainLain()
    {
        return $this->hasMany(LainLain::class, 'id_pegawai');
    }

    public function detailDempuls()
    {
        return $this->belongsToMany(
            DetailDempul::class,
            'detail_dempul_pegawai',
            'id_pegawai',
            'id_detail_dempul'
        );
    }

    public function perusahaan()
    {
        return $this->belongsTo(Perusahaan::class, 'perusahaan_id');
    }

    public function jabatan()
    {
        return $this->belongsTo(JabatanPerusahaan::class, 'jabatan_id');
    }


    // Pivot table pegawai dengan menggunakan detail hasil palet rotary
    public function detailHasilPaletRotaries(): BelongsToMany
    {
        return $this->belongsToMany(
            DetailHasilPaletRotary::class,
            'detail_hasil_palet_rotary_serah_terima_pivot',
            'id_pegawai',                                   // FK di tabel pivot untuk model ini (dibalik)
            'id_detail_hasil_palet_rotary'                  // FK di tabel pivot untuk model tujuan
        )
            ->withPivot('status')
            ->withTimestamps();
    }
}
