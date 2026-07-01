<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class JenisKayu extends Model
{

    protected $fillable = [
        'kode_kayu',
        'nama_kayu',
        'keterangan',
    ];
    public function detailPenggunaanJenisDiLahan()
    {
        return $this->hasMany(PenggunaanLahanRotary::class, 'id_jenis_kayu');
    }
    public function hargaKayu()
    {
        return $this->hasMany(HargaKayu::class, 'id_jenis_kayu', 'id');
    }
    public function detailKayuMasuk()
    {
        return $this->hasMany(DetailKayuMasuk::class, 'id_jenis_kayu', 'id');
    }
    public function detailTurusanKayus()
    {
        return $this->hasMany(DetailTurusanKayu::class, 'jenis_kayu_id');
    }

    public function rencanaPegawai()
    {
        return $this->hasMany(RencanaPegawai::class, 'id_jenis_kayu');
    }

    public function referensiHargaProduksis()
    {
        return $this->hasMany(ReferensiHargaProduksi::class, 'id_jenis_kayu');
    }
}
