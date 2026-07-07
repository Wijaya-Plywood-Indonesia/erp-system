<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class VeneerKeringMutasiKeluar extends Model
{
    protected $table = 'veneer_kering_mutasi_keluars';

    protected $fillable = [
        'id_ukuran',
        'id_jenis_kayu',
        'kw',
        'jumlah_palet',
        'qty',
        'm3',
        'tujuan_keluar',
        'dikeluarkan_oleh',
        'keterangan',
    ];

    protected $casts = [
        'jumlah_palet' => 'integer',
        'qty' => 'decimal:4',
        'm3' => 'decimal:6',
    ];

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'dikeluarkan_oleh');
    }

    public function palets()
    {
        return $this->hasMany(VeneerKeringMutasiKeluarPalet::class, 'id_mutasi_keluar')
            ->orderBy('no_palet');
    }
}