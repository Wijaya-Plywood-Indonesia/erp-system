<?php

namespace App\Models;

use App\Events\ProductionUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailBarangDikerjakan extends Model
{
    protected $table = 'detail_barang_dikerjakan';

    protected $fillable = [
        'id_produksi_nyusup',
        'id_pegawai_nyusup',
        'id_barang_setengah_jadi_hp',
        'no_palet',
        'modal',
        'hasil',
        'diserahkan_at',
        'diserahkan_by',
        'id_serah_terima_gudang_satu',
    ];

    protected $casts = [
        'diserahkan_at' => 'datetime',
    ];

    public function produksiNyusup()
    {
        return $this->belongsTo(ProduksiNyusup::class, 'id_produksi_nyusup');
    }

    public function PegawaiNyusup()
    {
        return $this->belongsTo(PegawaiNyusup::class, 'id_pegawai_nyusup');
    }

    public function barangSetengahJadiHp()
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_nyusup) {
                ProductionUpdated::dispatch($model->id_produksi_nyusup, 'nyusup');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_nyusup) {
                ProductionUpdated::dispatch($model->id_produksi_nyusup, 'nyusup');
            }
        });
    }

    public function serahTerima(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaGudangSatu::class, 'id_serah_terima_gudang_satu');
    }
}
