<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DetailTurunKayu extends Model
{
    // Nama tabel di database
    protected $table = 'detail_turun_kayus';

    // Daftar kolom yang diizinkan untuk diisi massal (Mass Assignment)
    protected $fillable = [
        'id_turun_kayu', // Foreign Key ke Parent
        'id_kayu_masuk', // Foreign Key ke Master Kayu
        'status',        // Status pengerjaan
        'nama_supir',    // Nama supir
        'jumlah_kayu',   // Jumlah kayu (Numeric)
        'foto',          // Path foto bukti
    ];

    /**
     * Relasi ke Header Transaksi (Parent)
     */
    public function turunKayu(): BelongsTo
    {
        return $this->belongsTo(TurunKayu::class, 'id_turun_kayu');
    }


    public function kayuMasuk(): BelongsTo
    {
        return $this->belongsTo(KayuMasuk::class, 'id_kayu_masuk');
    }
}