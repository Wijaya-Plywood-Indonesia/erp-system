<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class VeneerBasahMutasiDetail extends Model
{
    protected $table = 'veneer_basah_mutasi_details';

    protected $fillable = [
        'id_veneer_basah_mutasi',
        'id_ukuran',
        'id_jenis_kayu',
        'kw',
        'qty_lembar',
        'm3',
        'no_palet',
    ];

    protected $casts = [
        'qty_lembar' => 'integer',
        'm3' => 'float',
        'no_palet' => 'integer',
    ];

    public function mutasi(): BelongsTo
    {
        return $this->belongsTo(VeneerBasahMutasi::class, 'id_veneer_basah_mutasi');
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function serahTerima(): HasOne
    {
        return $this->hasOne(SerahTerimaVeneerBasah::class, 'id_veneer_basah_mutasi_detail');
    }
}