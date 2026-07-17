<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

class NotaBarangKeluar extends Model
{
    //
    protected $table = 'nota_barang_keluar';

    protected $fillable = [
        'tanggal',
        'no_nota',
        'tujuan_nota',
        'dibuat_oleh',
        'divalidasi_oleh',
    ];

   protected $casts = [
        'tanggal' => 'date',
        'dibuat_oleh' => 'integer',
        'divalidasi_oleh' => 'integer',
    ];

    // Relasi ke user pembuat
    public function pembuat()
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }

    // Relasi ke user validator
    public function validator()
    {
        return $this->belongsTo(User::class, 'divalidasi_oleh');
    }
    //relasi ke barang detail barang keluar
    public function detail()
    {
        return $this->hasMany(DetailNotaBarangKeluar::class, 'id_nota_bk');
    }

    // Relasi ke veneer mutasi
    public function mutasi()
    {
        return $this->hasOne(VeneerMutasi::class, 'id_nota_bk');
    }

    public function plywoodMutasi(): HasOne
{
    return $this->hasOne(PlywoodMutasi::class, 'id_nota_bk');
}
}
