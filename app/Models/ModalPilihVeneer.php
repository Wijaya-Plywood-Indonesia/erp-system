<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ModalPilihVeneer extends Model
{
    protected $table = 'modal_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_stok_veneer_jadi',
        'id_ukuran',
        'id_jenis_kayu',
        'no_palet',
        'kw',
        'jumlah',
    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function stokVeneerJadi()
    {
        return $this->belongsTo(StokVeneerJadi::class, 'id_stok_veneer_jadi');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function hasilPilihVeneers()
    {
        return $this->hasMany(HasilPilihVeneer::class, 'id_modal_pilih_veneer');
    }

    public function sisaBelumDipakai(?int $excludeHasilId = null): float
    {
        $terpakai = $this->hasilPilihVeneers()
            ->when($excludeHasilId, fn ($q) => $q->whereKeyNot($excludeHasilId))
            ->sum('jumlah');

        return (float) $this->jumlah - (float) $terpakai;
    }
}
