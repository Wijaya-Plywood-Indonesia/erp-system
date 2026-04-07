<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Model;

class ModalRepair extends Model
{
    protected $table = 'modal_repairs';

    protected $fillable = [
        'id_produksi_repair',
        'id_ukuran',
        'id_jenis_kayu',
        'jumlah',
        'kw',
        'nomor_palet',
        'keterangan',
    ];

    // Eager load biar nggak N+1 query
    protected $with = [
        'produksiRepair',
        'ukuran',
        'jenisKayu',
    ];

    public function produksiRepair(): BelongsTo
    {
        return $this->belongsTo(ProduksiRepair::class, 'id_produksi_repair');
    }

    /** Ukuran kayu */
    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    /** Jenis kayu */
    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function rencanaRepairs()
    {
        return $this->hasMany(RencanaRepair::class, 'id_modal_repair');
    }
}
