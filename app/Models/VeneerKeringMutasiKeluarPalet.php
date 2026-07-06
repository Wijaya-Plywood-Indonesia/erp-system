<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerKeringMutasiKeluarPalet extends Model
{
    protected $table = 'veneer_kering_mutasi_keluar_palets';

    protected $fillable = [
        'id_mutasi_keluar',
        'no_palet',
        'qty',
    ];

    protected $casts = [
        'no_palet' => 'integer',
        'qty' => 'decimal:4',
    ];

    public function mutasiKeluar()
    {
        return $this->belongsTo(VeneerKeringMutasiKeluar::class, 'id_mutasi_keluar');
    }

    // ─────────────────────────────────────────────
    // Accessor proxy ke header VeneerKeringMutasiKeluar
    //
    // Supaya model ini bisa diperlakukan SERAGAM seperti DetailHasil /
    // DetailBongkarKedi (yang punya kolom id_ukuran/id_jenis_kayu/kw
    // langsung) tanpa perlu ubah banyak logic existing di
    // StokVeneerKeringService & SerahTerimaVeneerKering::getSumberAttribute().
    // ─────────────────────────────────────────────

    public function getIdUkuranAttribute()
    {
        return $this->mutasiKeluar?->id_ukuran;
    }

    public function getIdJenisKayuAttribute()
    {
        return $this->mutasiKeluar?->id_jenis_kayu;
    }

    public function getKwAttribute()
    {
        return $this->mutasiKeluar?->kw;
    }

    public function ukuran()
    {
        return $this->mutasiKeluar?->ukuran;
    }

    public function jenisKayu()
    {
        return $this->mutasiKeluar?->jenisKayu;
    }
}