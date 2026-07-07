<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

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
    // Proxy ke header VeneerKeringMutasiKeluar, supaya palet bisa
    // diperlakukan SERAGAM seperti DetailHasil / DetailBongkarKedi.
    //
    // ukuran() & jenisKayu() harus berupa RELASI (bukan return model),
    // karena Filament/kode lain mengaksesnya sebagai properti
    // ($sumber->ukuran). Dipakai hasOneThrough menembus tabel header.
    // ─────────────────────────────────────────────

    public function ukuran(): HasOneThrough
    {
        return $this->hasOneThrough(
            Ukuran::class,                     // model tujuan
            VeneerKeringMutasiKeluar::class,   // model perantara (header)
            'id',                // FK di header yang dicocokkan ke palet (pakai id header)
            'id',                // PK di ukurans
            'id_mutasi_keluar',  // kolom lokal di palet -> header.id
            'id_ukuran'          // kolom di header -> ukurans.id
        );
    }

    public function jenisKayu(): HasOneThrough
    {
        return $this->hasOneThrough(
            JenisKayu::class,
            VeneerKeringMutasiKeluar::class,
            'id',
            'id',
            'id_mutasi_keluar',
            'id_jenis_kayu'
        );
    }

    // Atribut skalar cukup lewat accessor (bukan relasi, aman).
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
}