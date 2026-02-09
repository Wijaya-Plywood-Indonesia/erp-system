<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KontrakKerja extends Model
{
    //
    protected $table = 'kontrak_kerja';

    protected $fillable = [
        'kode',
        'nama',
        'jenis_kelamin',
        'tanggal_masuk',
        'karyawan_di',
        'alamat_perusahaan',
        'jabatan',
        'nik',
        'tempat_tanggal_lahir',
        'alamat',
        'no_telepon',
        'kontrak_mulai',
        'kontrak_selesai',
        'durasi_kontrak',
        'tanggal_kontrak',
        'no_kontrak',
        'status_dokumen',
        'bukti_ttd',
        'dibuat_oleh',
        'divalidasi_oleh',
        'status_kontrak',
        'keterangan',
    ];

    protected $casts = [
        'tanggal_masuk' => 'date',
        'kontrak_mulai' => 'date',
        'kontrak_selesai' => 'date',
    ];

    /*
    |--------------------------------------------------------------------------
    | SCOPES (Opsional tapi sangat berguna)
    |--------------------------------------------------------------------------
    */

    // public function scopeActive($query)
    // {
    //     return $query->where('status_kontrak', 'active');
    // }

    // public function scopeSoon($query)
    // {
    //     return $query->where('status_kontrak', 'soon');
    // }

    // public function scopeExpired($query)
    // {
    //     return $query->where('status_kontrak', 'expired');
    // }

    /*
    |--------------------------------------------------------------------------
    | ACCESSORS (Opsional)
    | Contoh: Hitung sisa hari kontrak
    |--------------------------------------------------------------------------
    */
    // public function getSisaHariAttribute()
    // {
    //     if (!$this->kontrak_selesai) {
    //         return null;
    //     }

    //     return now()->diffInDays($this->kontrak_selesai, false);
    // }

}
