<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KendaraanSupplierKayu extends Model
{
    //
    protected $table = 'kendaraan_supplier_kayus';
    protected $fillable = [
        'id_supplier',
        'nopol_kendaraan',
        'jenis_kendaraan',
        'pemilik_kendaraan',
    ];
    public function terdaftarKayuMasuk()
    {
        return $this->hasMany(KayuMasuk::class, 'id_kendaraan_supplier_kayus');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(SupplierKayu::class, 'id_supplier');
    }
}
