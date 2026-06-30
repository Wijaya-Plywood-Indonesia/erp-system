<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DetailBarangDikerjakanPotJelek extends Model
{
    protected $table = 'detail_barang_dikerjakan_pot_jelek';

    protected $fillable = [
        'id_produksi_pot_jelek',
        'id_pegawai_pot_jelek',
        'id_ukuran',
        'id_jenis_kayu',
        'tinggi',
        'kw',
        'no_palet',
    ];

    public function produksiPotJelek()
    {
        return $this->belongsTo(ProduksiPotJelek::class, 'id_produksi_pot_jelek');
    }

    public function PegawaiPotJelek()
    {
        return $this->belongsTo(PegawaiPotJelek::class, 'id_pegawai_pot_jelek');
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
            if ($model->id_produksi_pot_jelek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_jelek, 'pot_jelek');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_pot_jelek) {
                \App\Events\ProductionUpdated::dispatch($model->id_produksi_pot_jelek, 'pot_jelek');
            }
        });
    }
}
