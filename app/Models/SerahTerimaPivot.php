<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SerahTerimaPivot extends Model
{
    protected $table = 'detail_hasil_palet_rotary_serah_terima_pivot';

    protected $fillable = [
        'id_detail_hasil_palet_rotary',
        'diserahkan_oleh',
        'diterima_oleh',
        'tipe',
        'status',
    ];

    public function detailHasilPalet(): BelongsTo
    {
        return $this->belongsTo(
            DetailHasilPaletRotary::class,
            'id_detail_hasil_palet_rotary'
        );
    }
}
