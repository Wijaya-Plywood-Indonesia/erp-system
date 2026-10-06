<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilLogCore extends Model
{
    protected $fillable = [
        'produksi_rotary_id',
        'id_jenis_kayu',
        'panjang',
        'qty',
        'keterangan',
    ];

    /**
     * Casting tipe data.
     */
    protected $casts = [
        'panjang'           => 'integer',
        'qty'               => 'decimal:2',
    ];

    public function produksiRotary()
    {
        return $this->belongsTo(ProduksiRotary::class, 'produksi_rotary_id');
    }

    /**
     * Relasi ke Master Jenis Kayu
     */
    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function logStok(): \Illuminate\Database\Eloquent\Relations\MorphMany
    {
        return $this->morphMany(LogLogCore::class, 'referensi');
    }

    public function logTerakhir(): \Illuminate\Database\Eloquent\Relations\MorphOne
    {
        return $this->morphOne(LogLogCore::class, 'referensi')->latestOfMany();
    }

    public function getSudahDiserahAttribute(): bool
    {
        $status = array_key_exists('status_log_terakhir', $this->attributes)
            ? $this->attributes['status_log_terakhir']
            : $this->logStok()->latest('id')->value('tipe_transaksi');

        return $status === 'masuk';
    }
}
