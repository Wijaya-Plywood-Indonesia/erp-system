<?php

namespace App\Models;

use App\Events\ProductionUpdated;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DetailDempul extends Model
{
    protected $table = 'detail_dempuls';

    protected $fillable = [
        'id_produksi_dempul',
        'id_barang_setengah_jadi_hp',
        'id_serah_terima_triplek_cacat',
        'modal',
        'hasil',
        'nomor_palet',
        'jam_masuk',
        'jam_pulang',
        'ijin',
        'keterangan',
        'diserahkan_oleh',
        'diserahkan_at',
    ];

    public function produksiDempul(): BelongsTo
    {
        return $this->belongsTo(ProduksiDempul::class, 'id_produksi_dempul');
    }

    public function rencanaPegawaiDempul(): BelongsTo
    {
        return $this->belongsTo(RencanaPegawaiDempul::class, 'id_rencana_pegawai_dempul');
    }

    public function barangSetengahJadi(): BelongsTo
    {
        return $this->belongsTo(BarangSetengahJadiHp::class, 'id_barang_setengah_jadi_hp');
    }

    public function serahTerimaTriplekCacat(): BelongsTo
    {
        return $this->belongsTo(SerahTerimaTriplekCacat::class, 'id_serah_terima_triplek_cacat');
    }

    public function pegawais()
    {
        return $this->belongsToMany(
            Pegawai::class,
            'detail_dempul_pegawai',
            'id_detail_dempul',
            'id_pegawai'
        );
    }

    protected static function booted()
    {
        static::saved(function ($model) {
            if ($model->id_produksi_dempul) {
                ProductionUpdated::dispatch($model->id_produksi_dempul, 'dempul');
            }
        });

        static::deleted(function ($model) {
            if ($model->id_produksi_dempul) {
                ProductionUpdated::dispatch($model->id_produksi_dempul, 'dempul');
            }
        });
    }

    public function serahTerimaTriplekJadi()
    {
        return $this->hasOne(SerahTerimaTriplekJadi::class, 'id_detail_dempul');
    }
}
