<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ListPekerjaanMenumpuk extends Model
{
    protected $table = 'list_pekerjaan_menumpuk';

    protected $fillable = [
        'tanggal',
        'id_hasil_pilih_plywood', // Menghubungkan ke data cacat yang "reparasi"
        'id_barang_setengah_jadi_hp',
        'jumlah_asal',            // Jumlah awal yang harus diperbaiki
        'jumlah_selesai',         // Jumlah yang sudah diperbaiki
        'jumlah_belum_selesai',   // Field sisa yang akan otomatis terisi
        'status',                 // Selesai / Belum Selesai
    ];

    /**
     * Relasi ke sumber data hasil pilih (untuk ambil jenis cacat & jumlah asal)
     */
    public function hasilPilihPlywood(): BelongsTo
    {
        return $this->belongsTo(HasilPilihPlywood::class, 'id_hasil_pilih_plywood');
    }
    
    public function barangSetengahJadiHp(): BelongsTo
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    /**
     * Logika Otomatis: Dijalankan setiap kali data disimpan (Create/Update)
     */
    protected static function booted()
    {
        static::saving(function ($model) {
            // 1. Hitung otomatis sisa yang belum selesai
            $model->jumlah_belum_selesai = (int)$model->jumlah_asal - (int)$model->jumlah_selesai;

            // 2. Pastikan sisa tidak negatif
            if ($model->jumlah_belum_selesai < 0) {
                $model->jumlah_belum_selesai = 0;
            }

            // 3. Update status otomatis berdasarkan sisa
            $model->status = ($model->jumlah_belum_selesai <= 0) ? 'selesai' : 'belum selesai';
        });
    }
}