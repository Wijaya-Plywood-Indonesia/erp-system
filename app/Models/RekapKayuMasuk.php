<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RekapKayuMasuk extends Model
{
    protected $table = 'kayu_masuks';

    // read-only
    protected $guarded = ['*'];
    public $timestamps = false;

    // =======================
    // RELASI
    // =======================

    public function penggunaanSupplier()
    {
        return $this->belongsTo(SupplierKayu::class, 'id_supplier_kayus');
    }

    public function penggunaanKendaraanSupplier()
    {
        return $this->belongsTo(KendaraanSupplierKayu::class, 'id_kendaraan_supplier_kayus');
    }

    public function penggunaanDokumenKayu()
    {
        return $this->belongsTo(DokumenKayu::class, 'id_dokumen_kayus');
    }

    public function tempatKayu()
    {
        return $this->belongsTo(TempatKayu::class, 'id_tempat_kayu');
    }

    public function detailMasukanKayu(): HasMany
    {
        return $this->hasMany(DetailKayuMasuk::class, 'id_kayu_masuk');
    }

    public function detailTurunKayu(): HasMany
    {
        return $this->hasMany(DetailTurunKayu::class, 'id_kayu_masuk');
    }

    public function detailTurusanKayu(): HasMany
    {
        return $this->hasMany(DetailTurusanKayu::class, 'id_kayu_masuk');
    }

    public function detailMasuk(): HasMany
    {
        return $this->hasMany(DetailMasuk::class, 'id_kayu_masuk');
    }
    public function notaKayu(): HasMany
    {
        return $this->hasMany(NotaKayu::class, 'id_kayu_masuk');
    }
}
