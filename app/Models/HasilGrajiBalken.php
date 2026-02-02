<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HasilGrajiBalken extends Model
{
    protected $table = 'hasil_graji_balken';

    protected $fillable = [
        'id_produksi_graji_balken',
        'id_pegawai_graji_balken',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'no_palet',
    ];

    public function produksiGrajiBalken()
    {
        return $this->belongsTo(ProduksiGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function pegawaiGrajiBalken()
    {
        return $this->belongsTo(PegawaiGrajiBalken::class, 'id_produksi_graji_balken');
    }

    public function ukuran()
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu()
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    protected static function booted()
    {
        // Menggunakan static::saved mencakup Created dan Updated
        static::saved(function ($model) {
            if ($model->id_produksi_graji_balken) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_balken, 'graji_balken');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_graji_balken) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_graji_balken, 'graji_balken');
            }
        });
    }
}
