<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaPegawai extends Model
{
    use HasFactory;

    protected $table = 'rencana_pegawais';

    protected $fillable = [
        'id_produksi_repair',
        'id_pegawai',
        'nomor_meja',
        'jam_masuk',
        'jam_pulang',
        'ijin',
        'keterangan',
    ];

    protected $casts = [
        'jam_masuk' => 'datetime:H:i',
        'jam_pulang' => 'datetime:H:i',
    ];

    // Eager load biar nggak N+1 query
    protected $with = [
        'pegawai',
        'produksiRepair',
    ];

    // ==============================
    // RELASI
    // ==============================

    /** Relasi ke hari produksi */
    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    /** Relasi ke data pegawai */
    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function rencanaRepairs()
    {
        return $this->hasMany(RencanaRepair::class, 'id_rencana_pegawai');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_repair) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_repair, 'repair');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_repair) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_repair, 'repair');
            }
        });
    }
}
