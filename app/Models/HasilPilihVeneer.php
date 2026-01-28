<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilPilihVeneer extends Model
{
    protected $table = 'hasil_pilih_veneer';

    protected $fillable = [
        'id_produksi_pilih_veneer',
        'id_modal_pilih_veneer',
        'kw',
        'no_palet',
        'jumlah',
    ];

    public function produksiPilihVeneer()
    {
        return $this->belongsTo(ProduksiPilihVeneer::class, 'id_produksi_pilih_veneer');
    }

    public function modalPilihVeneer()
    {
        return $this->belongsTo(ModalPilihVeneer::class, 'id_modal_pilih_veneer');
    }

    // Tambahkan relasi pivot ke pegawai (seperti di Guellotine)
    public function pegawaiPilihVeneers()
    {
        return $this->belongsToMany(
            PegawaiPilihVeneer::class,
            'hasil_pilih_veneer_pegawai', // Pastikan tabel pivot ini ada di migration
            'id_hasil_pilih_veneer',
            'id_pegawai_pilih_veneer'
        );
    }
}