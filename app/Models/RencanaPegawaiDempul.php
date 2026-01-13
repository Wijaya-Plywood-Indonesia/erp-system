<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RencanaPegawaiDempul extends Model
{
    protected $table = 'rencana_pegawai_dempuls';

    protected $fillable = [
        'id_produksi_dempul',
        'id_pegawai',
        'jam_masuk',
        'jam_pulang',
        'ijin',
        'keterangan',
    ];

    public function pegawai(): BelongsTo
    {
        return $this->belongsTo(Pegawai::class, 'id_pegawai');
    }

    public function produksiDempul(): BelongsTo
    {
        return $this->belongsTo(ProduksiDempul::class, 'id_produksi_dempul');
    }
}
