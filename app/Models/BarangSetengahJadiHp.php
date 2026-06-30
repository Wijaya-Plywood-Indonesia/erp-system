<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BarangSetengahJadiHp extends Model
{
    protected $table = 'barang_setengah_jadi_hp';

    protected $fillable = [
        'id_jenis_barang',
        'id_ukuran',
        'id_grade',
        'keterangan',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisBarang()
    {
        return $this->belongsTo(JenisBarang::class, 'id_jenis_barang');
    }

    public function grade()
    {
        return $this->belongsTo(Grade::class, 'id_grade');
    }
    public function modalSandings()
    {
        return $this->hasMany(ModalSanding::class, 'id_barang_setengah_jadi');
    }

    public function detailDempuls()
    {
        return $this->hasMany(DetailDempul::class, 'id_barang_setengah_jadi_hp');
    }
}