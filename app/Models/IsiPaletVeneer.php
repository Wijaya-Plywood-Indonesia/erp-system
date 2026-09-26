<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class IsiPaletVeneer extends Model
{
    protected $table = 'isi_palet_veneers';

    protected $fillable = [
        'id_jenis_kayu',
        'id_ukuran',
        'jumlah_lembar',
        'keterangan',
    ];

    protected $casts = [
        'jumlah_lembar' => 'integer',
    ];

    public function jenisKayu(): BelongsTo
    {
        return $this->belongsTo(JenisKayu::class, 'id_jenis_kayu');
    }

    public function ukuran(): BelongsTo
    {
        return $this->belongsTo(Ukuran::class, 'id_ukuran');
    }
}
