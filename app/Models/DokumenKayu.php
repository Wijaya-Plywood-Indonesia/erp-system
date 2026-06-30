<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DokumenKayu extends Model
{
    //
    protected $table = 'dokumen_kayus';

    protected $fillable = [
        'nama_legal',
        'dokumen_legal',
        'no_dokumen_legal',
        'upload_dokumen',
        'upload_ktp',
        'foto_lokasi',

        //untuk Maps. 
        'alamat_lengkap', // hasil dari autocomplete Google Maps
        'latitude',   // -6.1753924 (misalnya)
        'longitude',  // 106.827153 (misalnya)
        'nama_tempat', // opsional, misal "Desa Jatirogo"
    ];

    /**
     * Format data lokasi jadi string singkat
     */
    public function getLokasiSingkatAttribute(): string
    {
        if ($this->latitude && $this->longitude) {
            return "{$this->latitude}, {$this->longitude}";
        }

        return '-';
    }
    public function terdaftarKayuMasuk()
    {
        return $this->hasMany(KayuMasuk::class, 'id_dokumen_kayus');
    }
}
